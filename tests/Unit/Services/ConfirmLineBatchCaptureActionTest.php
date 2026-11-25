<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\Actions\ConfirmLineBatchCaptureAction;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->stateStore = app(LineBatchCaptureStateStore::class);
});

it('confirms batch capture and clears state on success', function () {
    $fish = Fish::factory()->create();
    $action = new ConfirmLineBatchCaptureAction($this->stateStore, app(CaptureRecordBatchService::class));

    $this->stateStore->startSession('user-1', $fish->id);
    $this->stateStore->putImages('user-1', ['capture-1.jpg', 'capture-2.jpg']);
    $this->stateStore->putForm('user-1', [
        'tribe' => 'ivalino',
        'location' => 'Vanes',
        'capture_method' => 'mapazat',
        'capture_date' => '2026-05-16',
        'notes' => '測試備註',
    ]);

    $result = $action->execute('user-1');

    expect($result->successful())->toBeTrue()
        ->and($result->recordsCount())->toBe(2)
        ->and($result->message())->toBe('✅ 已成功新增 2 筆捕獲紀錄')
        ->and($this->stateStore->getState('user-1'))->toBeNull()
        ->and(CaptureRecord::where('fish_id', $fish->id)->whereNotNull('session_id')->count())->toBe(2);
});

it('returns failure and clears state when fish is missing during confirm', function () {
    $captureRecordBatchService = Mockery::mock(CaptureRecordBatchService::class);
    $action = new ConfirmLineBatchCaptureAction($this->stateStore, $captureRecordBatchService);

    $this->stateStore->startSession('user-1', 99999);

    $result = $action->execute('user-1');

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('❌ 魚類資料已不存在，請重新操作。')
        ->and($this->stateStore->getState('user-1'))->toBeNull();
});

it('returns validation failure without clearing state', function () {
    $fish = Fish::factory()->create();
    $action = new ConfirmLineBatchCaptureAction($this->stateStore, app(CaptureRecordBatchService::class));

    $this->stateStore->startSession('user-1', $fish->id);
    $this->stateStore->putImages('user-1', ['capture-1.jpg']);
    $this->stateStore->putForm('user-1', [
        'tribe' => 'ivalino',
        'location' => 'Vanes',
        'capture_method' => 'mapazat',
        'capture_date' => now()->addDays(3)->toDateString(),
    ]);

    $result = $action->execute('user-1');

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('捕獲日期不能是未來日期')
        ->and($this->stateStore->getState('user-1'))->toBe('waiting_images')
        ->and(CaptureRecord::count())->toBe(0);
});
