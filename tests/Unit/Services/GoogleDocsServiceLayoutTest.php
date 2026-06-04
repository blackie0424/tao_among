<?php

use App\Contracts\StorageServiceInterface;
use App\Services\GoogleDocsService;
use Google\Service\Docs;
use Google\Service\Docs\Document;
use Illuminate\Support\Collection;

function invokeGoogleDocsServiceMethod(object $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($service, $arguments);
}

function makeGoogleDocsServiceWithoutConstructor(): GoogleDocsService
{
    return (new ReflectionClass(GoogleDocsService::class))->newInstanceWithoutConstructor();
}

function makeFishLayoutsForDocExport(): Collection
{
    return collect([
        [
            'front' => [
                'heading' => '基本資料',
                'image_filename' => null,
                'name_line' => '名稱：飛魚',
                'qr_code_url' => null,
                'capture_method_line' => '捕獲方式：魚叉',
                'knowledge_heading' => '地方知識',
                'knowledge_table' => [
                    ['部落', '食用類別', '處理方式'],
                    ['Imowrod', 'oyod', '去魚鱗'],
                    ['Iraraley', 'rahet', '剝皮'],
                ],
            ],
            'back' => [
                'image_filename' => null,
                'lines' => [
                    '生態：洄游',
                    '分布：蘭嶼',
                    '傳統價值：祭儀',
                    '魚餌：小魚',
                ],
            ],
        ],
        [
            'front' => [
                'heading' => '基本資料',
                'image_filename' => null,
                'name_line' => '名稱：鬼頭刀',
                'qr_code_url' => null,
                'capture_method_line' => '捕獲方式：網捕',
                'knowledge_heading' => '地方知識',
                'knowledge_table' => [
                    ['部落', '食用類別', '處理方式'],
                    ['Imowrod', '尚未紀錄', '尚未紀錄'],
                    ['Iraraley', '尚未紀錄', '尚未紀錄'],
                ],
            ],
            'back' => [
                'image_filename' => null,
                'lines' => [
                    '生態：表層',
                    '分布：黑潮',
                    '傳統價值：分享',
                    '魚餌：飛魚',
                ],
            ],
        ],
    ]);
}

function makeRepeatedFishLayoutsForDocExport(int $count): Collection
{
    $baseLayout = makeFishLayoutsForDocExport()->first();

    return collect(range(1, $count))
        ->map(function (int $index) use ($baseLayout) {
            $layout = $baseLayout;
            $layout['front']['name_line'] = "名稱：測試魚 {$index}";
            $layout['back']['lines'][0] = "生態：測試 {$index}";

            return $layout;
        });
}

function makeHeavyFishLayoutsForDocExport(int $count): Collection
{
    return collect(range(1, $count))
        ->map(function (int $index) {
            return [
                'front' => [
                    'heading' => '基本資料',
                    'image_filename' => "front-{$index}.jpg",
                    'name_line' => "名稱：測試魚 {$index}",
                    'qr_code_url' => "https://example.com/qr/{$index}",
                    'capture_method_line' => "捕獲方式：方式 {$index}",
                    'knowledge_heading' => '地方知識',
                    'knowledge_table' => [
                        ['部落', '食用類別', '處理方式'],
                        ['Imowrod', "類別 {$index}", "處理 {$index}"],
                        ['Iraraley', "類別 {$index}", "處理 {$index}"],
                    ],
                ],
                'back' => [
                    'image_filename' => "back-{$index}.jpg",
                    'lines' => [
                        "生態：測試 {$index}",
                        "分布：區域 {$index}",
                        "傳統價值：價值 {$index}",
                        "魚餌：餌 {$index}",
                    ],
                ],
            ];
        });
}

function setGoogleDocsServiceProperty(object $service, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty($service, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($service, $value);
}

it('builds front and back pages in pairs for each fish layout', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();

    $pages = invokeGoogleDocsServiceMethod($service, 'buildPageBlueprints', makeFishLayoutsForDocExport());

    expect($pages)->toHaveCount(4)
        ->and(array_column($pages, 'side'))->toBe(['front', 'back', 'front', 'back']);

    $firstFrontTextBlocks = array_values(array_filter(
        $pages[0]['blocks'],
        fn (array $block) => $block['type'] === 'text'
    ));

    $firstBackTextBlocks = array_values(array_filter(
        $pages[1]['blocks'],
        fn (array $block) => $block['type'] === 'text'
    ));

    expect(array_map(
        fn (array $block) => [$block['content'], $block['style']],
        $firstFrontTextBlocks
    ))->toBe([
        ["基本資料\n", 'heading'],
        ["名稱：飛魚\n", 'normal'],
        ["捕獲方式：魚叉\n", 'normal'],
        ["地方知識\n", 'heading'],
    ])->and(array_map(
        fn (array $block) => [$block['content'], $block['style']],
        $firstBackTextBlocks
    ))->toBe([
        ["生態：洄游\n分布：蘭嶼\n傳統價值：祭儀\n魚餌：小魚\n", 'normal'],
    ]);
});

it('builds paged structure requests with only heading blocks styled as headings and all text at 14pt', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();

    $requests = invokeGoogleDocsServiceMethod($service, 'buildPagedStructureRequests', makeFishLayoutsForDocExport());

    $pageBreaks = array_values(array_filter(
        $requests,
        fn ($request) => $request->getInsertPageBreak() !== null
    ));

    $paragraphStyles = array_values(array_filter(
        $requests,
        fn ($request) => $request->getUpdateParagraphStyle() !== null
    ));

    $fontSizes = array_values(array_filter(
        $requests,
        fn ($request) => $request->getUpdateTextStyle() !== null
    ));

    $namedStyles = array_map(
        fn ($request) => $request->getUpdateParagraphStyle()->getParagraphStyle()->getNamedStyleType(),
        $paragraphStyles
    );

    expect($pageBreaks)->toHaveCount(3)
        ->and($paragraphStyles)->toHaveCount(10)
        ->and(array_count_values($namedStyles))->toBe([
            'NORMAL_TEXT' => 6,
            'HEADING_1' => 4,
        ])
        ->and($fontSizes)->not->toBeEmpty()
        ->and(collect($fontSizes)->every(fn ($request) => $request->getUpdateTextStyle()->getTextStyle()->getFontSize()->getMagnitude() === 14))->toBeTrue();
});

it('marks positioned table text as normal text', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();

    $requests = invokeGoogleDocsServiceMethod($service, 'buildPositionedTextRequests', 10, 'Imowrod');

    $paragraphStyleRequests = array_values(array_filter(
        $requests,
        fn ($request) => $request->getUpdateParagraphStyle() !== null
    ));

    expect($paragraphStyleRequests)->toHaveCount(1)
        ->and($paragraphStyleRequests[0]->getUpdateParagraphStyle()->getParagraphStyle()->getNamedStyleType())->toBe('NORMAL_TEXT');
});

it('uses google docs page break requests instead of form feed text', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();

    $pageBreakRequests = invokeGoogleDocsServiceMethod($service, 'buildPageBreakRequests');

    expect($pageBreakRequests)->toHaveCount(1)
        ->and($pageBreakRequests[0]->getInsertPageBreak())->not->toBeNull()
        ->and($pageBreakRequests[0]->getInsertText())->toBeNull()
        ->and($pageBreakRequests[0]->getInsertPageBreak()->getLocation()->getIndex())->toBe(1);
});

it('batches structure writes across many fish pages to stay well below one write per page', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();
    $document = new Document([
        'body' => [
            'content' => [
                ['endIndex' => 2],
            ],
        ],
    ]);

    $documentsResource = new class($document)
    {
        public array $batchUpdateCalls = [];

        public function __construct(private Document $document)
        {
        }

        public function get(string $docId): Document
        {
            return $this->document;
        }

        public function batchUpdate(string $docId, $batchRequest): void
        {
            $this->batchUpdateCalls[] = [
                'docId' => $docId,
                'requestCount' => count($batchRequest->getRequests()),
            ];
        }
    };

    $docsService = new class($documentsResource) extends Docs
    {
        public function __construct(object $documentsResource)
        {
            $this->documents = $documentsResource;
        }
    };

    setGoogleDocsServiceProperty($service, 'docsService', $docsService);

    $layouts = makeRepeatedFishLayoutsForDocExport(38);

    invokeGoogleDocsServiceMethod($service, 'buildAndApplyStructure', 'doc-123', $layouts);

    $pageCount = $layouts->count() * 2;

    expect($documentsResource->batchUpdateCalls)->not->toBeEmpty()
        ->and(count($documentsResource->batchUpdateCalls))->toBeLessThan($pageCount)
        ->and(count($documentsResource->batchUpdateCalls))->toBeLessThanOrEqual(3)
        ->and(collect($documentsResource->batchUpdateCalls)->every(fn (array $call) => $call['requestCount'] <= 400))->toBeTrue();
});

it('keeps 200 fish structure writes below quota even when batched image inserts fail', function () {
    $service = makeGoogleDocsServiceWithoutConstructor();
    $document = new Document([
        'body' => [
            'content' => [
                ['endIndex' => 2],
            ],
        ],
    ]);

    $documentsResource = new class($document)
    {
        public array $batchUpdateCalls = [];

        public function __construct(private Document $document)
        {
        }

        public function get(string $docId): Document
        {
            return $this->document;
        }

        public function batchUpdate(string $docId, $batchRequest): void
        {
            $requests = $batchRequest->getRequests();

            $this->batchUpdateCalls[] = [
                'docId' => $docId,
                'requestCount' => count($requests),
                'containsImage' => collect($requests)->contains(
                    fn ($request) => $request->getInsertInlineImage() !== null
                ),
            ];

            if (collect($requests)->contains(fn ($request) => $request->getInsertInlineImage() !== null)) {
                throw new Google\Service\Exception('image failure', 400);
            }
        }
    };

    $docsService = new class($documentsResource) extends Docs
    {
        public function __construct(object $documentsResource)
        {
            $this->documents = $documentsResource;
        }
    };

    setGoogleDocsServiceProperty($service, 'docsService', $docsService);
    setGoogleDocsServiceProperty($service, 'storage', new class implements StorageServiceInterface
    {
        public function createSignedUploadUrl(string $filePath, int $expiresIn = 60): ?string
        {
            return null;
        }

        public function createSignedUploadUrlForPendingAudio(int $fishId, string $ext = 'm4a', int $expiresIn = 300): ?array
        {
            return null;
        }

        public function moveObject(string $sourcePath, string $destPath): ?string
        {
            return null;
        }

        public function delete(string $filePath): bool
        {
            return true;
        }

        public function deleteWithValidation(string $filePath): array
        {
            return ['success' => true];
        }

        public function getImageFolder(): string
        {
            return 'images';
        }

        public function getAudioFolder(): string
        {
            return 'audio';
        }

        public function getWebpFolder(): string
        {
            return 'webp';
        }

        public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
        {
            return "https://example.com/{$type}/{$filename}";
        }

        public function uploadFile($file, string $path): string
        {
            return "https://example.com/{$path}";
        }

        public function fileExists(string $filePath): bool
        {
            return false;
        }
    });

    $layouts = makeHeavyFishLayoutsForDocExport(200);
    $requestGroups = invokeGoogleDocsServiceMethod($service, 'buildStructureRequestGroups', $layouts);
    $requestBatches = invokeGoogleDocsServiceMethod($service, 'buildRequestBatches', $requestGroups, 400);

    invokeGoogleDocsServiceMethod($service, 'buildAndApplyStructure', 'doc-200', $layouts);

    $expectedMaxCalls = count($requestBatches) * 2;

    expect(count($requestBatches))->toBeLessThan(60)
        ->and(count($documentsResource->batchUpdateCalls))->toBe($expectedMaxCalls)
        ->and($expectedMaxCalls)->toBeLessThan(60)
        ->and(collect($documentsResource->batchUpdateCalls)->every(fn (array $call) => $call['requestCount'] <= 400))->toBeTrue();
});
