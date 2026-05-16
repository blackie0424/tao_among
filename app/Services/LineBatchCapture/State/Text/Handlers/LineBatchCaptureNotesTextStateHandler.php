<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureNotesTextStateHandler implements LineBatchCaptureTextStateHandler
{
    public function states(): array
    {
        return [
            'awaiting_notes_prompt',
            'awaiting_notes_input',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void
    {
        [$validated, $error] = $flow->validateNotes($context->text());
        if ($validated === null) {
            $flow->replyText($context->replyToken(), $error);
            return;
        }

        $flow->updateForm($context->userId(), ['notes' => $validated['notes']]);
        $flow->putState($context->userId(), 'waiting_confirm');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
