<?php

use App\Models\Fish;
use App\Services\LineBatchCaptureReplyBuilder;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->builder = app(LineBatchCaptureReplyBuilder::class);
    $this->fish = new Fish([
        'id' => 1,
        'name' => 'Test Fish 8288',
    ]);
});

it('builds summary reply from state using presenter output', function () {
    $message = $this->builder->buildSummaryMessage(
        $this->fish,
        ['capture-1.jpg'],
        ['tribe' => 'ivalino'],
        'awaiting_location_prompt'
    );

    expect($message)->toBeInstanceOf(FlexMessage::class);

    $json = json_decode(json_encode($message), true);
    $texts = collect($json['contents']['body']['contents'])->pluck('text')->all();
    $buttons = collect($json['contents']['footer']['contents'])->map(fn ($item) => $item['action']['label'])->all();

    expect($texts)->toContain('已完成部落選擇，請輸入捕獲地點。')
        ->and($texts)->toContain('部落：ivalino')
        ->and($buttons)->toBe(['輸入捕獲地點', '修改部落', '❌ 取消']);
});

it('builds selector and text replies through dedicated builder methods', function () {
    $tribeMessage = $this->builder->buildTribeSelectionMessage('❌ 部落資料無效，請重新選擇。');
    $textMessage = $this->builder->buildTextMessage('測試文字');

    expect($tribeMessage)->toBeInstanceOf(FlexMessage::class)
        ->and($textMessage)->toBeInstanceOf(TextMessage::class);

    $tribeJson = json_decode(json_encode($tribeMessage), true);
    $bodyTexts = collect($tribeJson['contents']['body']['contents'])->pluck('text')->filter()->all();
    $descriptionText = $bodyTexts[1] ?? '';
    $textJson = json_decode(json_encode($textMessage), true);

    expect($bodyTexts)->toContain('請選擇捕獲部落')
        ->and($descriptionText)->toContain('❌ 部落資料無效，請重新選擇。')
        ->and($descriptionText)->toContain('點選後會回到摘要卡片繼續填寫。')
        ->and($textJson['text'])->toBe('測試文字');
});
