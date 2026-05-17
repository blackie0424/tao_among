<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureTribeSelectorTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return ['waiting_tribe_selection'];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        $flow->replyTribeSelectionCard($context->replyToken());
    }
}
