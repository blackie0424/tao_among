<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureDateSelectorTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return ['waiting_date_selection'];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        $flow->replyDateSelectionCard($context->replyToken());
    }
}
