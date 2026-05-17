<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureSummaryTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return [
            'waiting_images',
            'awaiting_method_prompt',
            'waiting_confirm',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
