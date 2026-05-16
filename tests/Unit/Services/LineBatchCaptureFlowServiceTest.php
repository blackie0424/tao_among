<?php

use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureCardService;
use App\Services\LineBatchCaptureFlowService;
use App\Services\LineBotService;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->lineBotService = Mockery::mock(LineBotService::class);
    $this->captureRecordBatchService = Mockery::mock(CaptureRecordBatchService::class);
    $this->lineBatchCaptureCardService = Mockery::mock(LineBatchCaptureCardService::class);
});

it('collects handled and protected actions from registered postback handlers', function () {
    $service = new LineBatchCaptureFlowService(
        $this->lineBotService,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureCardService,
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
    $this->lineBotService
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function (string $replyToken, array $messages) {
            expect($replyToken)->toBe('reply-token');
            $json = json_decode(json_encode($messages[0]), true);
            expect($json['text'])->toBe('handled by fake handler');
        });

    $service = new LineBatchCaptureFlowService(
        $this->lineBotService,
        $this->captureRecordBatchService,
        $this->lineBatchCaptureCardService,
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
