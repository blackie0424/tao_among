<?php

use App\Contracts\LineMessagingClientInterface;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCapture\State\Image\Handlers\LineBatchCaptureWaitingImagesImageStateHandler;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineImageSet;
use App\Services\LineBatchCaptureFlowService;
use App\Services\LineBatchCaptureMessageBuilder;
use App\Services\LineUploadService;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    Cache::flush();
    $this->lineMessagingClient = Mockery::mock(LineMessagingClientInterface::class);
    $this->captureRecordBatchService = Mockery::mock(CaptureRecordBatchService::class);
    $this->lineBatchCaptureMessageBuilder = Mockery::mock(LineBatchCaptureMessageBuilder::class);
    $this->lineUploadService = Mockery::mock(LineUploadService::class);
    $this->store = app(LineBatchCaptureStateStore::class);

    $this->flow = new LineBatchCaptureFlowService(
        $this->lineMessagingClient,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureMessageBuilder,
        $this->lineUploadService,
    );

    $this->store->startSession('user-1', 1);
    $this->handler = new LineBatchCaptureWaitingImagesImageStateHandler();
});

it('uploads image and shows summary without imageSet', function () {
    $this->lineUploadService->shouldReceive('uploadLineImage')->with('msg-1')->andReturn('a.jpg');
    $this->lineMessagingClient->shouldReceive('replyMessage')->once();

    $this->handler->handle($this->flow, new LineBatchCaptureImageContext(
        'waiting_images', 'user-1', 'reply-token', 'msg-1'
    ));

    expect($this->store->getImages('user-1'))->toBe(['a.jpg'])
        ->and($this->store->getState('user-1'))->toBe('waiting_images');
});

it('stores image by index when imageSet is present but not yet complete', function () {
    $this->lineUploadService->shouldReceive('uploadLineImage')->with('msg-1')->andReturn('a.jpg');
    $this->lineMessagingClient->shouldReceive('replyMessage')->once();

    $imageSet = new LineImageSet('set-abc', 1, 3);
    $this->handler->handle($this->flow, new LineBatchCaptureImageContext(
        'waiting_images', 'user-1', 'reply-token', 'msg-1', $imageSet
    ));

    expect($this->store->getImages('user-1'))->toBe([])
        ->and($this->store->getState('user-1'))->toBe('waiting_images');

    [$setId, $indexed, $total] = $this->store->getIndexedImages('user-1');
    expect($setId)->toBe('set-abc')
        ->and($indexed)->toBe([1 => 'a.jpg'])
        ->and($total)->toBe(3);
});

it('auto-transitions to tribe selection when imageSet is complete', function () {
    $this->lineUploadService->shouldReceive('uploadLineImage')->andReturn('c.jpg');
    $this->store->putIndexedImages('user-1', ['set-abc', [1 => 'a.jpg', 2 => 'b.jpg'], 3]);

    $this->lineMessagingClient->shouldReceive('replyMessage')->once();

    $imageSet = new LineImageSet('set-abc', 3, 3);
    $this->handler->handle($this->flow, new LineBatchCaptureImageContext(
        'waiting_images', 'user-1', 'reply-token', 'msg-3', $imageSet
    ));

    expect($this->store->getState('user-1'))->toBe('waiting_tribe_selection')
        ->and($this->store->getImages('user-1'))->toBe(['a.jpg', 'b.jpg', 'c.jpg'])
        ->and($this->store->getIndexedImages('user-1'))->toBeNull();
});

it('respects max images limit even with imageSet', function () {
    config(['fish_options.batch_upload.max_files_mobile' => 2]);
    $this->store->putImages('user-1', ['x.jpg', 'y.jpg']);

    $this->lineMessagingClient->shouldReceive('replyMessage')->once();

    $imageSet = new LineImageSet('set-abc', 1, 3);
    $this->handler->handle($this->flow, new LineBatchCaptureImageContext(
        'waiting_images', 'user-1', 'reply-token', 'msg-1', $imageSet
    ));

    expect($this->store->getImages('user-1'))->toHaveCount(2);
});
