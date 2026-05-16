<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureDatePostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'open_batch_capture_date_selector',
            'set_batch_capture_date',
            'request_manual_batch_capture_date',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'open_batch_capture_date_selector') {
            $form = $flow->getForm($context->userId());
            if (empty($form['capture_method'])) {
                $flow->replySummary($context->replyToken(), $context->userId());
                return;
            }

            $flow->putState($context->userId(), 'waiting_date_selection');
            $flow->replyDateSelectionCard($context->replyToken());
            return;
        }

        if ($context->action() === 'request_manual_batch_capture_date') {
            $flow->putState($context->userId(), 'awaiting_date_manual_input');
            $flow->replyText($context->replyToken(), '請輸入捕獲日期，格式為 YYYY-MM-DD：');
            return;
        }

        $captureDate = match ($context->params()['value'] ?? null) {
            'today' => now()->toDateString(),
            'yesterday' => now()->subDay()->toDateString(),
            default => null,
        };

        if (!$captureDate) {
            $flow->replyDateSelectionCard($context->replyToken(), '❌ 日期選項無效，請重新選擇。');
            return;
        }

        $flow->updateForm($context->userId(), ['capture_date' => $captureDate]);
        $flow->putState($context->userId(), 'awaiting_notes_prompt');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
