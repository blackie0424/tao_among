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
    private const BLOCK_TYPE_TEXT = 'text';
    private const BLOCK_TYPE_IMAGE = 'image';
    private const BLOCK_TYPE_TABLE = 'table';
    private const TEXT_STYLE_NORMAL = 'normal';
    private const TEXT_STYLE_HEADING = 'heading';
    private const PAGE_SIDE_FRONT = 'front';
    private const PAGE_SIDE_BACK = 'back';
    private const IMAGE_SOURCE_FISH = 'fish';
    private const IMAGE_SOURCE_DIRECT = 'direct';

    private const SUPPORTED_IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'svg',
    ];

    private const DEFAULT_FONT_SIZE_PT = 14;

    private Docs $docsService;

    public function __construct(
        ?StorageServiceInterface $storage = null,
        ?FishCatalogLayoutBuilder $layoutBuilder = null,
    ) {
        $this->storage = $storage ?? app(StorageServiceInterface::class);
        $this->layoutBuilder = $layoutBuilder ?? app(FishCatalogLayoutBuilder::class);
        $this->docsService = $this->makeDocsService();
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

        $requestGroups = $this->buildStructureRequestGroups($layouts);
        $this->executeRequestGroups($docId, $requestGroups);
    }

    private function buildPageBlueprints(Collection $layouts): array
    {
        $pages = [];

        foreach ($layouts->values() as $layout) {
            $pages[] = $this->buildFrontPageBlueprint($layout['front']);
            $pages[] = $this->buildBackPageBlueprint($layout['back']);
        }

        return $pages;
    }

    private function buildPagedStructureRequests(Collection $layouts): array
    {
        $requests = [];

        foreach ($this->buildStructureRequestGroups($layouts) as $requestGroup) {
            $requests = array_merge($requests, $requestGroup);
        }

        return $requests;
    }

    private function buildStructureRequestGroups(Collection $layouts): array
    {
        $pages = $this->buildPageBlueprints($layouts);
        $requestGroups = [];
        $lastPageIndex = count($pages) - 1;

        for ($i = $lastPageIndex; $i >= 0; $i--) {
            $pageRequests = $this->buildPageRequests($pages[$i], $i < $lastPageIndex);

            if (!empty($pageRequests)) {
                $requestGroups[] = $pageRequests;
            }
        }

        return $requestGroups;
    }

    private function executeRequestGroups(string $docId, array $requestGroups, int $maxRequestsPerWrite = 400): void
    {
        foreach ($this->buildRequestBatches($requestGroups, $maxRequestsPerWrite) as $requestBatch) {
            $this->executeRequestBatch($docId, $requestBatch);
        }
    }

    private function buildRequestBatches(array $requestGroups, int $maxRequestsPerWrite = 400): array
    {
        $requestBatches = [];
        $pendingRequests = [];

        foreach ($requestGroups as $requestGroup) {
            if (!empty($pendingRequests) && (count($pendingRequests) + count($requestGroup)) > $maxRequestsPerWrite) {
                $requestBatches[] = $pendingRequests;
                $pendingRequests = [];
            }

            $pendingRequests = array_merge($pendingRequests, $requestGroup);
        }

        if (!empty($pendingRequests)) {
            $requestBatches[] = $pendingRequests;
        }

        return $requestBatches;
    }

    private function executeRequestBatch(string $docId, array $requests): void
    {
        if (empty($requests)) {
            return;
        }

        try {
            $this->executeBatchUpdate($docId, $requests);
        } catch (\Google\Service\Exception $e) {
            $fallbackRequests = array_values(array_filter(
                $requests,
                fn (DocsRequest $request) => $request->getInsertInlineImage() === null
            ));

            if (!empty($fallbackRequests)) {
                $this->executeBatchUpdate($docId, $fallbackRequests);
            }
        }
    }

    private function buildFrontPageBlueprint(array $frontLayout): array
    {
        $blocks = [
            $this->makeTextBlock("{$frontLayout['heading']}\n", self::TEXT_STYLE_HEADING),
        ];

        if (filled($frontLayout['image_filename'])) {
            $blocks[] = $this->makeFishImageBlock($frontLayout['image_filename'], 220, 220);
        }

        $blocks[] = $this->makeTextBlock("{$frontLayout['name_line']}\n");

        if (filled($frontLayout['qr_code_url'])) {
            $blocks[] = $this->makeDirectImageBlock($frontLayout['qr_code_url'], 160, 160);
        }

        $blocks[] = $this->makeTextBlock("{$frontLayout['capture_method_line']}\n");
        $blocks[] = $this->makeTextBlock("{$frontLayout['knowledge_heading']}\n", self::TEXT_STYLE_HEADING);
        $blocks[] = $this->makeTableBlock(3, 3);

        return [
            'side' => self::PAGE_SIDE_FRONT,
            'blocks' => $blocks,
        ];
    }

    private function buildBackPageBlueprint(array $backLayout): array
    {
        $blocks = [];

        if (filled($backLayout['image_filename'])) {
            $blocks[] = $this->makeFishImageBlock($backLayout['image_filename'], 220, 220);
        }

        $blocks[] = $this->makeTextBlock(implode("\n", $backLayout['lines']) . "\n");

        return [
            'side' => self::PAGE_SIDE_BACK,
            'blocks' => $blocks,
        ];
    }

    private function buildPageRequests(array $page, bool $insertPageBreakAfter): array
    {
        $requests = [];

        if ($insertPageBreakAfter) {
            $requests = array_merge($requests, $this->buildPageBreakRequests());
        }

        return array_merge($requests, $this->buildBlockRequests($page['blocks']));
    }

    private function buildBlockRequests(array $blocks): array
    {
        $requests = [];

        foreach (array_reverse($blocks) as $block) {
            $requests = array_merge($requests, $this->buildRequestsForBlock($block));
        }

        return $requests;
    }

    private function buildRequestsForBlock(array $block): array
    {
        return match ($block['type']) {
            self::BLOCK_TYPE_TEXT => $this->buildTextRequests(
                $block['content'],
                $this->resolveParagraphStyle($block['style'])
            ),
            self::BLOCK_TYPE_TABLE => $this->buildTableInsertRequests($block['rows'], $block['columns']),
            self::BLOCK_TYPE_IMAGE => $this->buildImageBlockRequests($block),
            default => [],
        };
    }

    private function buildImageBlockRequests(array $block): array
    {
        $uri = $this->resolveBlockImageUri($block);

        if (!$uri) {
            return [];
        }

        return $this->buildInlineImageRequests($uri, $block['width'], $block['height']);
    }

    private function resolveBlockImageUri(array $block): ?string
    {
        if (($block['source'] ?? null) === self::IMAGE_SOURCE_DIRECT) {
            return $block['uri'] ?? null;
        }

        if (($block['source'] ?? null) !== self::IMAGE_SOURCE_FISH) {
            return null;
        }

        return $this->getAccessibleImageUrl(
            $this->storage->getImageFolder(),
            $block['filename'] ?? null
        );
    }

    private function resolveParagraphStyle(string $style): ?string
    {
        return $style === self::TEXT_STYLE_HEADING ? 'HEADING_1' : null;
    }

    private function makeTextBlock(string $content, string $style = self::TEXT_STYLE_NORMAL): array
    {
        return [
            'type' => self::BLOCK_TYPE_TEXT,
            'content' => $content,
            'style' => $style,
        ];
    }

    private function makeTableBlock(int $rows, int $columns): array
    {
        return [
            'type' => self::BLOCK_TYPE_TABLE,
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    private function makeFishImageBlock(string $filename, int $width, int $height): array
    {
        return [
            'type' => self::BLOCK_TYPE_IMAGE,
            'source' => self::IMAGE_SOURCE_FISH,
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function makeDirectImageBlock(string $uri, int $width, int $height): array
    {
        return [
            'type' => self::BLOCK_TYPE_IMAGE,
            'source' => self::IMAGE_SOURCE_DIRECT,
            'uri' => $uri,
            'width' => $width,
            'height' => $height,
        ];
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
                'insertPageBreak' => [
                    'location' => ['index' => 1],
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
