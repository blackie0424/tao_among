<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\Request as DocsRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GoogleDocsService
{
    private Docs $docsService;
    private StorageServiceInterface $storage;

    public function __construct()
    {
        $credentialsPath = storage_path(
            config('services.google.credentials_path', 'app/google-credentials.json')
        );

        $client = new Client();
        $client->setApplicationName('Tao Among Fish Export');
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Docs::DOCUMENTS]);

        $this->docsService = new Docs($client);
        $this->storage = app(StorageServiceInterface::class);
    }

    /**
     * 取得可讓 Google Docs API 存取的圖片 URL（S3 預簽章 URL，有效 1 小時）。
     * 若非 S3 driver 則直接回傳原始 URL。
     */
    private function getAccessibleImageUrl(string $folder, string $filename): ?string
    {
        if (empty($filename) || $filename === 'default.png') {
            return null;
        }

        $path = $folder . '/' . $filename;

        try {
            /** @var \Illuminate\Contracts\Filesystem\Cloud $disk */
            $disk = Storage::disk('s3');

            // 確認檔案存在再產生 URL，避免 Google Docs API 收到不存在的圖片
            if (!$disk->exists($path)) {
                return null;
            }

            return $disk->temporaryUrl($path, now()->addHour());
        } catch (\Exception $e) {
            // 非 S3 或不支援 temporaryUrl，回傳一般 URL
            return $this->storage->getUrl('images', $filename, false);
        }
    }

    /**
     * 將所有魚類資料匯出至指定的 Google Docs 文件（每次覆蓋）。
     */
    public function exportFishes(string $docId, Collection $fishes): void
    {
        // Phase 1：清除文件並插入完整結構（含空白表格）
        $this->buildAndApplyStructure($docId, $fishes);

        // Phase 2：取得更新後的文件，填入表格內容
        $doc = $this->docsService->documents->get($docId);
        $fillRequests = $this->buildTableFillRequests($doc, $fishes);
        if (!empty($fillRequests)) {
            $this->chunkAndExecute($docId, $fillRequests);
        }
    }

    // -------------------------------------------------------------------------
    // Phase 1：結構建立
    // -------------------------------------------------------------------------

    private function buildAndApplyStructure(string $docId, Collection $fishes): void
    {
        $doc = $this->docsService->documents->get($docId);
        $endIndex = $this->getBodyEndIndex($doc);

        // 清除現有內容
        if ($endIndex > 2) {
            $this->executeBatchUpdate($docId, [
                new DocsRequest([
                    'deleteContentRange' => [
                        'range' => ['startIndex' => 1, 'endIndex' => $endIndex - 1],
                    ],
                ]),
            ]);
        }

        // 以「倒序插入到 position 1」的方式建立內容
        // 陣列中越後面的請求 = 越晚執行 = 插入後出現在文件越前面
        $fishList = $fishes->values()->all();
        $lastFishIndex = count($fishList) - 1;

        // 將所有魚的請求按魚為單位分批（避免超過 API 每批 500 個請求上限）
        $currentBatch = [];
        $currentCount = 0;

        for ($i = $lastFishIndex; $i >= 0; $i--) {
            $fishReqs = $this->buildFishRequests($fishList[$i], $i === $lastFishIndex);
            $fishReqCount = count($fishReqs);

            // 若加入後超過 400，先送出目前批次再開新批次
            if ($currentCount + $fishReqCount > 400 && !empty($currentBatch)) {
                $this->executeBatchUpdate($docId, $currentBatch);
                $currentBatch = [];
                $currentCount = 0;
            }

            foreach ($fishReqs as $req) {
                $currentBatch[] = $req;
            }
            $currentCount += $fishReqCount;
        }

        if (!empty($currentBatch)) {
            $this->executeBatchUpdate($docId, $currentBatch);
        }
    }

    /**
     * 為單隻魚建立插入請求（倒序插入邏輯）。
     *
     * 陣列中「較早」的請求 = 較早執行 = 插入後被後續請求推到文件後方。
     * 陣列中「較晚」的請求 = 較晚執行 = 插入後出現在文件前方（越接近頂部）。
     *
     * 目標文件結構（由上至下）：
     *   [魚名 HEADING_1]
     *   [首圖]
     *   [基本資料 HEADING_3]
     *   [名稱：...]
     *   [發音：點此播放（超連結）]
     *   [地方知識 HEADING_3]
     *   [空白表格（phase 2 填入）]
     *   [最近捕獲紀錄 HEADING_3]
     *   [捕獲時間/地點/方式]
     *   [捕獲圖片]
     *   [\f 分頁（非最後一隻魚）]
     */
    private function buildFishRequests(Fish $fish, bool $isLastFish): array
    {
        $requests = [];

        // --- 1. 分頁符號（出現在本魚區塊最末端）---
        if (!$isLastFish) {
            $requests[] = new DocsRequest([
                'insertText' => ['location' => ['index' => 1], 'text' => "\f"],
            ]);
        }

        // --- 2. 最近捕獲紀錄 ---
        $latestCapture = $fish->captureRecords->first();
        if ($latestCapture) {
            // 捕獲圖片（最末端：先插入 \n 讓圖片出現在 \n 之前）
            $captureImageUrl = $latestCapture->image_path
                ? $this->getAccessibleImageUrl($this->storage->getImageFolder(), $latestCapture->image_path)
                : null;
            if ($captureImageUrl) {
                $requests[] = new DocsRequest([
                    'insertText' => ['location' => ['index' => 1], 'text' => "\n"],
                ]);
                $requests[] = new DocsRequest([
                    'insertInlineImage' => [
                        'location' => ['index' => 1],
                        'uri' => $captureImageUrl,
                        'objectSize' => [
                            'height' => ['magnitude' => 150, 'unit' => 'PT'],
                            'width' => ['magnitude' => 200, 'unit' => 'PT'],
                        ],
                    ],
                ]);
            }

            // 捕獲時間、地點、方式（依序插入，整塊一次）
            $detailLines = [];
            if ($latestCapture->capture_method) {
                $detailLines[] = "捕獲方式：{$latestCapture->capture_method}";
            }
            if ($latestCapture->location) {
                $detailLines[] = "捕獲地點：{$latestCapture->location}";
            }
            if ($latestCapture->capture_date) {
                $detailLines[] = "捕獲時間：" . $latestCapture->capture_date->format('Y/m/d');
            }
            if (!empty($detailLines)) {
                $requests[] = new DocsRequest([
                    'insertText' => [
                        'location' => ['index' => 1],
                        'text' => implode("\n", $detailLines) . "\n",
                    ],
                ]);
            }

            // 捕獲紀錄區塊標題
            $captureHeader = "最近捕獲紀錄\n";
            $requests[] = new DocsRequest([
                'insertText' => ['location' => ['index' => 1], 'text' => $captureHeader],
            ]);
            $requests[] = new DocsRequest([
                'updateParagraphStyle' => [
                    'range' => ['startIndex' => 1, 'endIndex' => 1 + mb_strlen($captureHeader)],
                    'paragraphStyle' => ['namedStyleType' => 'HEADING_3'],
                    'fields' => 'namedStyleType',
                ],
            ]);
        }

        // --- 3. 地方知識（部落分類表格）---
        $exportTribes = array_values(array_intersect(
            config('fish_options.tribes', []),
            ['imowrod', 'iraraley']
        ));
        $classifications = $fish->tribalClassifications
            ->whereIn('tribe', $exportTribes)
            ->values();

        if ($classifications->count() > 0) {
            // 行數 = 標題列 + 部落數（從 config 動態計算），確保 Phase 2 填表時行列對齊
            $requests[] = new DocsRequest([
                'insertTable' => [
                    'rows' => count($exportTribes) + 1,
                    'columns' => 3,
                    'location' => ['index' => 1],
                ],
            ]);

            $knowledgeHeader = "地方知識\n";
            $requests[] = new DocsRequest([
                'insertText' => ['location' => ['index' => 1], 'text' => $knowledgeHeader],
            ]);
            $requests[] = new DocsRequest([
                'updateParagraphStyle' => [
                    'range' => ['startIndex' => 1, 'endIndex' => 1 + mb_strlen($knowledgeHeader)],
                    'paragraphStyle' => ['namedStyleType' => 'HEADING_3'],
                    'fields' => 'namedStyleType',
                ],
            ]);
        }

        // --- 4. 基本資料 ---
        // 發音連結（主音檔）
        if ($fish->audio_url) {
            $prefixText = "發音：";
            $linkText = "點此播放";
            $audioLine = $prefixText . $linkText . "\n";
            $linkStart = 1 + mb_strlen($prefixText);
            $linkEnd = $linkStart + mb_strlen($linkText);

            $requests[] = new DocsRequest([
                'insertText' => ['location' => ['index' => 1], 'text' => $audioLine],
            ]);
            $requests[] = new DocsRequest([
                'updateTextStyle' => [
                    'range' => ['startIndex' => $linkStart, 'endIndex' => $linkEnd],
                    'textStyle' => ['link' => ['url' => $fish->audio_url]],
                    'fields' => 'link',
                ],
            ]);
        }

        // 名稱
        $requests[] = new DocsRequest([
            'insertText' => [
                'location' => ['index' => 1],
                'text' => "名稱：{$fish->name}\n",
            ],
        ]);

        // 基本資料標題
        $basicHeader = "基本資料\n";
        $requests[] = new DocsRequest([
            'insertText' => ['location' => ['index' => 1], 'text' => $basicHeader],
        ]);
        $requests[] = new DocsRequest([
            'updateParagraphStyle' => [
                'range' => ['startIndex' => 1, 'endIndex' => 1 + mb_strlen($basicHeader)],
                'paragraphStyle' => ['namedStyleType' => 'HEADING_3'],
                'fields' => 'namedStyleType',
            ],
        ]);

        // --- 5. 首圖（插入 \n 讓圖片出現在 \n 之前）---
        if ($fish->image && $fish->image !== 'default.png') {
            $fishImageUrl = $this->getAccessibleImageUrl($this->storage->getImageFolder(), $fish->image);
            if ($fishImageUrl) {
                $requests[] = new DocsRequest([
                    'insertText' => ['location' => ['index' => 1], 'text' => "\n"],
                ]);
                $requests[] = new DocsRequest([
                    'insertInlineImage' => [
                        'location' => ['index' => 1],
                        'uri' => $fishImageUrl,
                        'objectSize' => [
                            'height' => ['magnitude' => 200, 'unit' => 'PT'],
                            'width' => ['magnitude' => 200, 'unit' => 'PT'],
                        ],
                    ],
                ]);
            }
        }

        // --- 6. 魚名大標題（最後插入 = 出現在文件最頂端）---
        $nameHeading = "{$fish->name}\n";
        $requests[] = new DocsRequest([
            'insertText' => ['location' => ['index' => 1], 'text' => $nameHeading],
        ]);
        $requests[] = new DocsRequest([
            'updateParagraphStyle' => [
                'range' => ['startIndex' => 1, 'endIndex' => 1 + mb_strlen($nameHeading)],
                'paragraphStyle' => ['namedStyleType' => 'HEADING_1'],
                'fields' => 'namedStyleType',
            ],
        ]);

        return $requests;
    }

    // -------------------------------------------------------------------------
    // Phase 2：填入表格內容
    // -------------------------------------------------------------------------

    private function buildTableFillRequests(Docs\Document $doc, Collection $fishes): array
    {
        // 取出文件中所有表格（依出現順序）
        $tables = [];
        foreach ($doc->getBody()->getContent() as $element) {
            if ($element->getTable() !== null) {
                $tables[] = $element->getTable();
            }
        }

        $allInserts = [];
        $tableIndex = 0;
        $targetTribes = array_values(array_intersect(
            config('fish_options.tribes', []),
            ['imowrod', 'iraraley']
        ));

        foreach ($fishes as $fish) {
            // 與 Phase 1 相同的條件：有任一匯出部落資料才有表格
            $hasClassification = $fish->tribalClassifications
                ->whereIn('tribe', $targetTribes)
                ->count() > 0;

            if (!$hasClassification) {
                continue;
            }

            if (!isset($tables[$tableIndex])) {
                break;
            }

            $table = $tables[$tableIndex++];
            $tableRows = $table->getTableRows();

            // 表頭列：部落、食用類別、處理方式
            if (isset($tableRows[0])) {
                $headerCells = $tableRows[0]->getTableCells();
                foreach (['部落', '食用類別', '處理方式'] as $colIdx => $header) {
                    if (isset($headerCells[$colIdx])) {
                        $pos = $headerCells[$colIdx]->getContent()[0]->getStartIndex();
                        $allInserts[] = ['pos' => $pos, 'text' => $header];
                    }
                }
            }

            // 資料列：以 tribe 名稱為 key 查找，依 export_tribes 設定順序
            // 若該部落無資料，顯示「尚未紀錄」
            $classificationsByTribe = $fish->tribalClassifications
                ->whereIn('tribe', $targetTribes)
                ->keyBy('tribe');

            foreach ($targetTribes as $rowIdx => $tribe) {
                $rowIndex = $rowIdx + 1;
                if (!isset($tableRows[$rowIndex])) {
                    continue;
                }
                $cells = $tableRows[$rowIndex]->getTableCells();
                $c = $classificationsByTribe->get($tribe);
                $values = [
                    $this->formatTribeName($tribe),
                    $c ? ($c->food_category ?: '尚未紀錄') : '尚未紀錄',
                    $c ? ($c->processing_method ?: '尚未紀錄') : '尚未紀錄',
                ];
                foreach ($values as $colIdx => $value) {
                    if (isset($cells[$colIdx])) {
                        $pos = $cells[$colIdx]->getContent()[0]->getStartIndex();
                        $allInserts[] = ['pos' => $pos, 'text' => $value];
                    }
                }
            }
        }

        if (empty($allInserts)) {
            return [];
        }

        // 由高位置到低位置排序，避免插入後位移影響後續請求
        usort($allInserts, fn ($a, $b) => $b['pos'] - $a['pos']);

        return array_map(fn ($insert) => new DocsRequest([
            'insertText' => [
                'location' => ['index' => $insert['pos']],
                'text' => $insert['text'],
            ],
        ]), $allInserts);
    }

    // -------------------------------------------------------------------------
    // 工具方法
    // -------------------------------------------------------------------------

    private function formatTribeName(string $tribe): string
    {
        return match ($tribe) {
            'imowrod' => 'Imowrod',
            'iraraley' => 'Iraraley',
            default => ucfirst($tribe),
        };
    }

    private function getBodyEndIndex(Docs\Document $doc): int
    {
        $content = $doc->getBody()->getContent();
        if (empty($content)) {
            return 1;
        }
        return end($content)->getEndIndex();
    }

    private function chunkAndExecute(string $docId, array $requests, int $size = 400): void
    {
        foreach (array_chunk($requests, $size) as $chunk) {
            $this->executeBatchUpdate($docId, $chunk);
        }
    }

    private function executeBatchUpdate(string $docId, array $requests): void
    {
        if (empty($requests)) {
            return;
        }
        $batchRequest = new BatchUpdateDocumentRequest();
        $batchRequest->setRequests($requests);
        $this->docsService->documents->batchUpdate($docId, $batchRequest);
    }
}
