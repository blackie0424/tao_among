<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureMethodSelectorTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return ['waiting_method_selection'];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        $flow->replyMethodSelectionCard($context->replyToken());
    }
}
