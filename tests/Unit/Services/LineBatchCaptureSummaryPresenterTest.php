<?php

use App\Services\LineBatchCaptureSummaryPresenter;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->presenter = app(LineBatchCaptureSummaryPresenter::class);
});

it('presents waiting images state with upload guidance and cancel only when empty', function () {
    $view = $this->presenter->present('waiting_images', [], []);

    expect($view['notice'])->toBe('請先上傳至少 1 張捕獲照片，全部上傳完成後再進入下一步。')
        ->and($view['actions'])->toBe([
            ['label' => '❌ 取消', 'data' => 'action=cancel_batch_capture_record', 'display_text' => '取消批次新增捕獲紀錄'],
        ]);
});

it('presents waiting confirm state with submit reset and cancel actions', function () {
    $view = $this->presenter->present('waiting_confirm', ['capture-1.jpg'], [
        'tribe' => 'ivalino',
        'location' => 'Vanes',
        'capture_method' => 'mapazat',
        'capture_date' => '2026-05-16',
    ]);

    expect($view['notice'])->toBe('請確認資料無誤後再送出。')
        ->and($view['actions'][0]['label'])->toBe('✅ 確認送出')
        ->and($view['actions'][1]['label'])->toBe('🔁 重新填寫')
        ->and($view['actions'][2]['label'])->toBe('❌ 取消');
});
