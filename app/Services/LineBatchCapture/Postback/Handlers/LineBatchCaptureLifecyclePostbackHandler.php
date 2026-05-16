<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureLifecyclePostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'start_batch_capture_record',
            'reset_batch_capture_form',
            'confirm_batch_capture_record',
            'cancel_batch_capture_record',
        ];
    }

    public function protectedActions(): array
    {
        return [
            'start_batch_capture_record',
            'reset_batch_capture_form',
            'confirm_batch_capture_record',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        match ($context->action()) {
            'start_batch_capture_record' => $flow->startCapture(
                $context->userId(),
                $context->replyToken(),
                (int) ($context->params()['fish_id'] ?? 0)
            ),
            'reset_batch_capture_form' => $this->resetForm($flow, $context),
            'confirm_batch_capture_record' => $flow->confirmCapture($context->userId(), $context->replyToken()),
            'cancel_batch_capture_record' => $this->cancel($flow, $context),
        };
    }

    private function resetForm(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        $flow->putForm($context->userId(), []);
        $flow->putState($context->userId(), 'waiting_images');
        $flow->replySummary($context->replyToken(), $context->userId());
    }

    private function cancel(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        $flow->clearState($context->userId());
        $flow->replyText($context->replyToken(), '✅ 已取消批次新增捕獲紀錄');
    }
}
