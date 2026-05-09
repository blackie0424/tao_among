<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Services\GoogleDocs\FishCatalogLayoutBuilder;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\Request as DocsRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GoogleDocsService
{
    private const SUPPORTED_IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'svg',
    ];

    private const DEFAULT_FONT_SIZE_PT = 14;

    private Docs $docsService;

    public function __construct(
        ?StorageServiceInterface $storage = null,
        ?FishCatalogLayoutBuilder $layoutBuilder = null,
        ?Docs $docsService = null,
    ) {
        $this->storage = $storage ?? app(StorageServiceInterface::class);
        $this->layoutBuilder = $layoutBuilder ?? app(FishCatalogLayoutBuilder::class);
        $this->docsService = $docsService ?? $this->makeDocsService();
    }

    public function exportFishes(string $docId, Collection $fishes): void
    {
        $layouts = $fishes
            ->values()
            ->map(fn ($fish) => $this->layoutBuilder->build($fish));

        $this->buildAndApplyStructure($docId, $layouts);

        $doc = $this->docsService->documents->get($docId);
        $fillRequests = $this->buildTableFillRequests($doc, $layouts);
        unset($doc);

        if (!empty($fillRequests)) {
            $this->chunkAndExecute($docId, $fillRequests);
        }
    }

    private StorageServiceInterface $storage;

    private FishCatalogLayoutBuilder $layoutBuilder;

    private function makeDocsService(): Docs
    {
        $credentialsPath = storage_path(
            config('services.google.credentials_path', 'app/google-credentials.json')
        );

        $client = new Client();
        $client->setApplicationName('Tao Among Fish Export');
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Docs::DOCUMENTS]);

        return new Docs($client);
    }

    private function buildAndApplyStructure(string $docId, Collection $layouts): void
    {
        $doc = $this->docsService->documents->get($docId);
        $endIndex = $this->getBodyEndIndex($doc);

        if ($endIndex > 2) {
            $this->executeBatchUpdate($docId, [
                new DocsRequest([
                    'deleteContentRange' => [
                        'range' => ['startIndex' => 1, 'endIndex' => $endIndex - 1],
                    ],
                ]),
            ]);
        }

        $layoutList = $layouts->values()->all();
        $lastIndex = count($layoutList) - 1;

        for ($i = $lastIndex; $i >= 0; $i--) {
            $fishRequests = $this->buildFishRequests($layoutList[$i], $i === $lastIndex);

            if (empty($fishRequests)) {
                continue;
            }

            try {
                $this->executeBatchUpdate($docId, $fishRequests);
            } catch (\Google\Service\Exception $e) {
                $fallbackRequests = array_values(array_filter(
                    $fishRequests,
                    fn (DocsRequest $request) => $request->getInsertInlineImage() === null
                ));

                if (!empty($fallbackRequests)) {
                    $this->executeBatchUpdate($docId, $fallbackRequests);
                }
            }
        }
    }

    private function buildFishRequests(array $layout, bool $isLastFish): array
    {
        $requests = [];

        if (!$isLastFish) {
            $requests = array_merge($requests, $this->buildPageBreakRequests());
        }

        $backImageUrl = $this->getAccessibleImageUrl(
            $this->storage->getImageFolder(),
            $layout['back']['image_filename']
        );

        $requests = array_merge($requests, $this->buildBackRequests($layout['back'], $backImageUrl));
        $requests = array_merge($requests, $this->buildPageBreakRequests());

        $frontImageUrl = $this->getAccessibleImageUrl(
            $this->storage->getImageFolder(),
            $layout['front']['image_filename']
        );

        $requests = array_merge($requests, $this->buildFrontRequests($layout['front'], $frontImageUrl));

        return $requests;
    }

    private function buildFrontRequests(array $frontLayout, ?string $frontImageUrl): array
    {
        $requests = [];

        $requests = array_merge($requests, $this->buildTableInsertRequests(3, 3));
        $requests = array_merge($requests, $this->buildTextRequests("{$frontLayout['knowledge_heading']}\n", 'HEADING_1'));
        $requests = array_merge($requests, $this->buildTextRequests("{$frontLayout['capture_method_line']}\n"));

        if ($frontLayout['qr_code_url']) {
            $requests = array_merge($requests, $this->buildInlineImageRequests($frontLayout['qr_code_url'], 160, 160));
        }

        $requests = array_merge($requests, $this->buildTextRequests("{$frontLayout['name_line']}\n"));

        if ($frontImageUrl) {
            $requests = array_merge($requests, $this->buildInlineImageRequests($frontImageUrl, 220, 220));
        }

        $requests = array_merge($requests, $this->buildTextRequests("{$frontLayout['heading']}\n", 'HEADING_1'));

        return $requests;
    }

    private function buildBackRequests(array $backLayout, ?string $backImageUrl): array
    {
        $requests = [];
        $backText = implode("\n", $backLayout['lines']) . "\n";

        $requests = array_merge($requests, $this->buildTextRequests($backText));

        if ($backImageUrl) {
            $requests = array_merge($requests, $this->buildInlineImageRequests($backImageUrl, 220, 220));
        }

        return $requests;
    }

    private function buildTableFillRequests(Docs\Document $doc, Collection $layouts): array
    {
        $tables = [];

        foreach ($doc->getBody()->getContent() as $element) {
            if ($element->getTable() !== null) {
                $tables[] = $element->getTable();
            }
        }

        $operations = [];

        foreach ($layouts->values() as $tableIndex => $layout) {
            if (!isset($tables[$tableIndex])) {
                break;
            }

            $tableRows = $tables[$tableIndex]->getTableRows();

            foreach ($layout['front']['knowledge_table'] as $rowIndex => $row) {
                if (!isset($tableRows[$rowIndex])) {
                    continue;
                }

                $cells = $tableRows[$rowIndex]->getTableCells();

                foreach ($row as $columnIndex => $value) {
                    if (!isset($cells[$columnIndex])) {
                        continue;
                    }

                    $operations[] = [
                        'position' => $cells[$columnIndex]->getContent()[0]->getStartIndex(),
                        'text' => $value,
                    ];
                }
            }
        }

        usort($operations, fn ($left, $right) => $right['position'] <=> $left['position']);

        $requests = [];
        foreach ($operations as $operation) {
            $requests = array_merge(
                $requests,
                $this->buildPositionedTextRequests($operation['position'], $operation['text'])
            );
        }

        return $requests;
    }

    private function buildTextRequests(string $text, ?string $paragraphStyle = null): array
    {
        $start = 1;
        $end = $start + mb_strlen($text);

        $requests = [
            new DocsRequest([
                'insertText' => [
                    'location' => ['index' => $start],
                    'text' => $text,
                ],
            ]),
        ];

        if ($paragraphStyle) {
            $requests[] = new DocsRequest([
                'updateParagraphStyle' => [
                    'range' => ['startIndex' => $start, 'endIndex' => $end],
                    'paragraphStyle' => ['namedStyleType' => $paragraphStyle],
                    'fields' => 'namedStyleType',
                ],
            ]);
        }

        $requests[] = $this->buildFontSizeRequest($start, $end);

        return $requests;
    }

    private function buildPositionedTextRequests(int $position, string $text): array
    {
        $end = $position + mb_strlen($text);

        return [
            new DocsRequest([
                'insertText' => [
                    'location' => ['index' => $position],
                    'text' => $text,
                ],
            ]),
            $this->buildFontSizeRequest($position, $end),
        ];
    }

    private function buildInlineImageRequests(string $uri, int $width, int $height): array
    {
        return [
            new DocsRequest([
                'insertText' => [
                    'location' => ['index' => 1],
                    'text' => "\n",
                ],
            ]),
            new DocsRequest([
                'insertInlineImage' => [
                    'location' => ['index' => 1],
                    'uri' => $uri,
                    'objectSize' => [
                        'height' => ['magnitude' => $height, 'unit' => 'PT'],
                        'width' => ['magnitude' => $width, 'unit' => 'PT'],
                    ],
                ],
            ]),
        ];
    }

    private function buildTableInsertRequests(int $rows, int $columns): array
    {
        return [
            new DocsRequest([
                'insertTable' => [
                    'rows' => $rows,
                    'columns' => $columns,
                    'location' => ['index' => 1],
                ],
            ]),
        ];
    }

    private function buildPageBreakRequests(): array
    {
        return [
            new DocsRequest([
                'insertText' => [
                    'location' => ['index' => 1],
                    'text' => "\f",
                ],
            ]),
        ];
    }

    private function buildFontSizeRequest(int $start, int $end): DocsRequest
    {
        return new DocsRequest([
            'updateTextStyle' => [
                'range' => ['startIndex' => $start, 'endIndex' => $end],
                'textStyle' => [
                    'fontSize' => [
                        'magnitude' => self::DEFAULT_FONT_SIZE_PT,
                        'unit' => 'PT',
                    ],
                ],
                'fields' => 'fontSize',
            ],
        ]);
    }

    private function getAccessibleImageUrl(string $folder, ?string $filename): ?string
    {
        if (empty($filename) || $filename === 'default.png') {
            return null;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::SUPPORTED_IMAGE_EXTENSIONS, true)) {
            return null;
        }

        $path = $folder . '/' . $filename;

        try {
            $disk = Storage::disk('s3');
            if (!$disk->exists($path)) {
                return null;
            }

            return $disk->temporaryUrl($path, now()->addHour());
        } catch (\Exception $e) {
            return $this->storage->getUrl('images', $filename, false);
        }
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
