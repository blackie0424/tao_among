<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;
use Illuminate\Validation\ValidationException;

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
        try {
            $validated = $flow->captureRecordFieldValidator()->validateCaptureDate($context->text());
            $flow->updateForm($context->userId(), ['capture_date' => $validated['capture_date']]);
            $flow->putState($context->userId(), 'awaiting_notes_prompt');
            $flow->replySummary($context->replyToken(), $context->userId());
        } catch (ValidationException $e) {
            $flow->replyText($context->replyToken(), $e->errors()['capture_date'][0] ?? '請輸入捕獲日期');
        }
    }
}
