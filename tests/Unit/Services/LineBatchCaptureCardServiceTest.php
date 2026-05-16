<?php

use App\Models\Fish;
use App\Services\LineBatchCaptureCardService;
use LINE\Clients\MessagingApi\Model\FlexMessage;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new LineBatchCaptureCardService();
    $this->fish = new Fish([
        'id' => 1,
        'name' => 'Test Fish 8288',
    ]);
});

it('builds progressive summary card with actual filled values', function () {
    $message = $this->service->buildSummaryCard(
        $this->fish,
        ['capture-1.jpg', 'capture-2.jpg'],
        [
            'tribe' => 'ivalino',
            'location' => 'Vanes',
            'capture_method' => 'mapazat',
            'capture_date' => '2026-05-16',
            'notes' => '金鰲跟魯凱',
        ],
        [
            ['label' => '選擇捕獲方式', 'data' => 'action=open_batch_capture_method_selector'],
            ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record'],
        ]
    );

    expect($message)->toBeInstanceOf(FlexMessage::class);

    $json = json_decode(json_encode($message), true);
    $texts = collect($json['contents']['body']['contents'])->pluck('text')->all();
    $buttons = collect($json['contents']['footer']['contents'])->map(fn ($item) => $item['action']['label'])->all();

    expect($texts)->toContain('魚類：Test Fish 8288');
    expect($texts)->toContain('照片數量：2 張');
    expect($texts)->toContain('部落：ivalino');
    expect($texts)->toContain('地點：Vanes');
    expect($texts)->toContain('捕獲方式：mapazat 網魚');
    expect($texts)->toContain('日期：2026-05-16');
    expect($texts)->toContain('備註：金鰲跟魯凱');
    expect($buttons)->toContain('選擇捕獲方式');
    expect($buttons)->toContain('❌ 取消');
});

it('builds option selector card with buttons in flex body', function () {
    $message = $this->service->buildOptionSelectorCard(
        '請選擇捕獲方式',
        '選好後會回到摘要卡片繼續填寫。',
        [
            ['label' => 'mapazat 網魚', 'data' => 'action=select_batch_capture_method&capture_method=mapazat'],
            ['label' => 'mamasil 白天釣魚', 'data' => 'action=select_batch_capture_method&capture_method=mamasil'],
        ]
    );

    expect($message)->toBeInstanceOf(FlexMessage::class);

    $json = json_decode(json_encode($message), true);
    $bodyContents = collect($json['contents']['body']['contents']);
    $bodyTexts = $bodyContents->pluck('text')->filter()->all();
    $buttons = $bodyContents
        ->filter(fn ($item) => ($item['type'] ?? null) === 'button')
        ->map(fn ($item) => $item['action']['label'])
        ->all();

    expect($bodyTexts)->toContain('請選擇捕獲方式');
    expect($bodyTexts)->toContain('選好後會回到摘要卡片繼續填寫。');
    expect($buttons)->toContain('mapazat 網魚');
    expect($buttons)->toContain('mamasil 白天釣魚');
});
