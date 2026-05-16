<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureDateTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return [
            'awaiting_date_prompt',
            'awaiting_date_manual_input',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        [$validated, $error] = $flow->validateCaptureDate($context->text());
        if (!$validated) {
            $flow->replyText($context->replyToken(), $error);
            return;
        }

        $flow->updateForm($context->userId(), ['capture_date' => $validated['capture_date']]);
        $flow->putState($context->userId(), 'awaiting_notes_prompt');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
