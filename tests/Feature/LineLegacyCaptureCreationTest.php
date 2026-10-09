<?php

use App\Contracts\LineMessagingClientInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\Actions\ConfirmLineBatchCaptureAction;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCaptureMessageBuilder;
use App\Services\LineCreateFish\LineCreateFishFormFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function legacyLineForm(): array
{
    return [
        'tribe' => 'ivalino',
        'location' => 'LINE 測試地點',
        'capture_method' => 'mamasil',
        'capture_date' => '2026-05-16',
        'notes' => 'LINE 舊流程',
    ];
}

it('keeps ConfirmLineBatchCaptureAction on legacy fields without a session id', function () {
    $fish = Fish::factory()->create();
    $store = app(LineBatchCaptureStateStore::class);
    $store->startSession('line-confirm-user', $fish->id);
    $store->putImages('line-confirm-user', ['line-confirm.jpg']);
    $store->putForm('line-confirm-user', legacyLineForm());

    $result = (new ConfirmLineBatchCaptureAction($store, app(CaptureRecordBatchService::class)))
        ->execute('line-confirm-user');
    $record = CaptureRecord::firstOrFail();

    expect($result->successful())->toBeTrue()
        ->and($record->session_id)->toBeNull()
        ->and($record->tribe)->toBe('ivalino')
        ->and($record->location)->toBe('LINE 測試地點')
        ->and($record->capture_method)->toBe('mamasil');
});

it('keeps LineCreateFishFormFlowService on legacy fields without a session id', function () {
    $fish = Fish::factory()->create(['display_capture_record_id' => null]);
    $messaging = Mockery::mock(LineMessagingClientInterface::class);
    $messaging->shouldReceive('replyMessage')->once();
    $flow = new LineCreateFishFormFlowService(
        $messaging,
        app(CaptureRecordBatchService::class),
        app(LineBatchCaptureMessageBuilder::class),
    );
    $store = $flow->lineBatchCaptureStateStore();
    $store->putFishId('line-create-user', $fish->id);
    $store->putImages('line-create-user', ['line-create.jpg']);
    $store->putForm('line-create-user', legacyLineForm());

    $flow->confirmCapture('line-create-user', 'reply-token');
    $record = CaptureRecord::firstOrFail();

    expect($record->session_id)->toBeNull()
        ->and($record->tribe)->toBe('ivalino')
        ->and($record->location)->toBe('LINE 測試地點')
        ->and($record->capture_method)->toBe('mamasil')
        ->and($fish->fresh()->display_capture_record_id)->toBe($record->id);
});
