<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;
use Illuminate\Validation\ValidationException;

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
        try {
            $validated = $flow->captureRecordFieldValidator()->validateNotes($context->text());
            $flow->updateForm($context->userId(), ['notes' => $validated['notes']]);
            $flow->putState($context->userId(), 'waiting_confirm');
            $flow->replySummary($context->replyToken(), $context->userId());
        } catch (ValidationException $e) {
            $flow->replyText($context->replyToken(), $e->errors()['notes'][0] ?? '備註格式錯誤');
        }
    }
}
