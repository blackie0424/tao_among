<?php

namespace App\Services\LineBatchCapture\State\Text\Handlers;

use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextContext;
use App\Services\LineBatchCapture\State\Text\LineBatchCaptureTextStateHandler;
use App\Services\LineBatchCaptureFlowService;
use Illuminate\Validation\ValidationException;

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
        try {
            $validated = $flow->captureRecordFieldValidator()->validateLocation($context->text());
            $flow->updateForm($context->userId(), ['location' => $validated['location']]);
            $flow->putState($context->userId(), 'awaiting_method_prompt');
            $flow->replySummary($context->replyToken(), $context->userId());
        } catch (ValidationException $e) {
            $flow->replyText($context->replyToken(), $e->errors()['location'][0] ?? '請輸入捕獲地點');
        }
    }
}
