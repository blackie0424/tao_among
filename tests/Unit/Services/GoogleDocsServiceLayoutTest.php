<?php

use App\Services\GoogleDocsService;
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

    $pageBreaks = array_values(array_filter($requests, function ($request) {
        $insertText = $request->getInsertText();

        return $insertText !== null && $insertText->getText() === "\f";
    }));

    $paragraphStyles = array_values(array_filter(
        $requests,
        fn ($request) => $request->getUpdateParagraphStyle() !== null
    ));

    $fontSizes = array_values(array_filter(
        $requests,
        fn ($request) => $request->getUpdateTextStyle() !== null
    ));

    expect($pageBreaks)->toHaveCount(3)
        ->and($paragraphStyles)->toHaveCount(4)
        ->and(array_map(
            fn ($request) => $request->getUpdateParagraphStyle()->getParagraphStyle()->getNamedStyleType(),
            $paragraphStyles
        ))->toBe(['HEADING_1', 'HEADING_1', 'HEADING_1', 'HEADING_1'])
        ->and($fontSizes)->not->toBeEmpty()
        ->and(collect($fontSizes)->every(fn ($request) => $request->getUpdateTextStyle()->getTextStyle()->getFontSize()->getMagnitude() === 14))->toBeTrue();
});
