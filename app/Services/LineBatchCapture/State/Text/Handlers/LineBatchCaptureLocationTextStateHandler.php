<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureLocationTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return [
            'awaiting_location_prompt',
            'awaiting_location_input',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        [$validated, $error] = $flow->validateLocation($context->text());
        if (!$validated) {
            $flow->replyText($context->replyToken(), $error);
            return;
        }

        $flow->updateForm($context->userId(), ['location' => $validated['location']]);
        $flow->putState($context->userId(), 'awaiting_method_prompt');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
