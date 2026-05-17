<?php

use App\Services\CaptureRecordBatchService;
use App\Contracts\LineMessagingClientInterface;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageStateHandler;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;
use App\Services\LineBatchCaptureMessageBuilder;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    Cache::flush();
    $this->lineMessagingClient = Mockery::mock(LineMessagingClientInterface::class);
    $this->captureRecordBatchService = Mockery::mock(CaptureRecordBatchService::class);
    $this->lineBatchCaptureMessageBuilder = Mockery::mock(LineBatchCaptureMessageBuilder::class);
});

it('collects handled and protected actions from registered postback handlers', function () {
    $service = new LineBatchCaptureFlowService(
        $this->lineMessagingClient,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureMessageBuilder,
        null,
        [
            new class implements LineBatchCapturePostbackHandler {
                public function actions(): array
                {
                    return ['custom-start'];
                }

                public function protectedActions(): array
                {
                    return ['custom-start'];
                }

                public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
                {
                }
            },
            new class implements LineBatchCapturePostbackHandler {
                public function actions(): array
                {
                    return ['custom-cancel'];
                }

                public function protectedActions(): array
                {
                    return [];
                }

                public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
                {
                }
            },
        ]
    );

    expect($service->handlesPostback('custom-start'))->toBeTrue()
        ->and($service->handlesPostback('custom-cancel'))->toBeTrue()
        ->and($service->handlesPostback('unknown-action'))->toBeFalse()
        ->and($service->protectedActions())->toBe(['custom-start']);
});

it('dispatches postback handling to the matching handler', function () {
    $this->lineMessagingClient
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function (string $replyToken, array $messages) {
            expect($replyToken)->toBe('reply-token');
            $json = json_decode(json_encode($messages[0]), true);
            expect($json['text'])->toBe('handled by fake handler');
        });

    $service = new LineBatchCaptureFlowService(
        $this->lineMessagingClient,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureMessageBuilder,
        null,
        [
            new class implements LineBatchCapturePostbackHandler {
                public function actions(): array
                {
                    return ['custom-action'];
                }

                public function protectedActions(): array
                {
                    return ['custom-action'];
                }

                public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
                {
                    $flow->replyText($context->replyToken(), 'handled by fake handler');
                }
            },
        ]
    );

    expect($service->handlePostback('user-1', 'reply-token', 'custom-action', ['foo' => 'bar']))->toBeTrue();
});

it('dispatches text handling to the matching state handler', function () {
    Cache::put('line_user_user-1_batch_capture_state', 'custom-text-state', now()->addMinutes(15));

    $this->lineMessagingClient
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function (string $replyToken, array $messages) {
            expect($replyToken)->toBe('reply-token');
            $json = json_decode(json_encode($messages[0]), true);
            expect($json['text'])->toBe('handled by fake text handler');
        });

    $service = new LineBatchCaptureFlowService(
        $this->lineMessagingClient,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureMessageBuilder,
        null,
        [],
        [
            new class implements LineBatchCaptureTextStateHandler {
                public function states(): array
                {
                    return ['custom-text-state'];
                }

                public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
                {
                    $flow->replyText($context->replyToken(), 'handled by fake text handler');
                }
            },
        ]
    );

    expect($service->handleTextMessage('user-1', 'hello', 'reply-token'))->toBeTrue();
});

it('dispatches image handling to the matching state handler', function () {
    Cache::put('line_user_user-1_batch_capture_state', 'custom-image-state', now()->addMinutes(15));

    $this->lineMessagingClient
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function (string $replyToken, array $messages) {
            expect($replyToken)->toBe('reply-token');
            $json = json_decode(json_encode($messages[0]), true);
            expect($json['text'])->toBe('handled by fake image handler');
        });

    $service = new LineBatchCaptureFlowService(
        $this->lineMessagingClient,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureMessageBuilder,
        null,
        [],
        [],
        [
            new class implements LineBatchCaptureImageStateHandler {
                public function states(): array
                {
                    return ['custom-image-state'];
                }

                public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureImageContext $context): void
                {
                    $flow->replyText($context->replyToken(), 'handled by fake image handler');
                }
            },
        ]
    );

    expect($service->handleImageMessage('user-1', 'reply-token', 'message-1'))->toBeTrue();
});
