<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureUploadPostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'continue_batch_capture_upload',
            'finish_batch_capture_upload',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'continue_batch_capture_upload') {
            $flow->putState($context->userId(), 'waiting_images');
            $flow->replySummary($context->replyToken(), $context->userId());
            return;
        }

        if ($flow->getImages($context->userId()) === []) {
            $flow->putState($context->userId(), 'waiting_images');
            $flow->replySummary($context->replyToken(), $context->userId());
            return;
        }

        $flow->replySessionPickerOrTribeCard($context->replyToken(), $context->userId());
    }
}
