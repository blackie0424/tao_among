<?php

use App\Models\CaptureRecord;
use App\Services\LineBatchCaptureReplyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('提供兩種船釣捕獲方式', function () {
    expect(config('fish_options.capture_methods'))
        ->toHaveKey('船釣（拼板舟）', '船釣（拼板舟）')
        ->toHaveKey('船釣（機動船）', '船釣（機動船）');
});

it('所有捕獲方式 key 都不含會破壞 postback query 的字元', function () {
    foreach (array_keys(config('fish_options.capture_methods')) as $method) {
        expect($method)->not->toMatch('/[.+&]/');
    }
});

it('所有捕獲方式都能通過 LINE postback query 往返', function () {
    $message = app(LineBatchCaptureReplyBuilder::class)->buildMethodSelectionMessage();
    $json = json_decode(json_encode($message), true);
    $buttons = $json['contents']['body']['contents'] ?? [];
    $postbacks = collect($buttons)
        ->filter(fn (array $item) => isset($item['action']['data']))
        ->mapWithKeys(fn (array $item) => [$item['action']['label'] => $item['action']['data']]);

    foreach (config('fish_options.capture_methods') as $method => $label) {
        parse_str($postbacks->get($label), $parsed);

        expect($parsed['capture_method'] ?? null)->toBe($method);
    }
});

it('兩種船釣捕獲方式都能寫入資料庫並原值讀回', function (string $method) {
    $record = CaptureRecord::factory()->create(['capture_method' => $method]);

    expect($record->fresh()->capture_method)->toBe($method);
})->with([
    '拼板舟' => '船釣（拼板舟）',
    '機動船' => '船釣（機動船）',
]);
