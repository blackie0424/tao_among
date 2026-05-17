<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureMethodPostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'open_batch_capture_method_selector',
            'select_batch_capture_method',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'open_batch_capture_method_selector') {
            $form = $flow->getForm($context->userId());
            if (empty($form['tribe']) || empty($form['location'])) {
                $flow->replySummary($context->replyToken(), $context->userId());
                return;
            }

            $flow->putState($context->userId(), 'waiting_method_selection');
            $flow->replyMethodSelectionCard($context->replyToken());
            return;
        }

        $captureMethod = $context->params()['capture_method'] ?? null;
        $validMethods = array_keys(config('fish_options.capture_methods', []));

        if (!$captureMethod || !in_array($captureMethod, $validMethods, true)) {
            $flow->replyMethodSelectionCard($context->replyToken(), '❌ 捕獲方式無效，請重新選擇。');
            return;
        }

        $flow->updateForm($context->userId(), ['capture_method' => $captureMethod]);
        $flow->putState($context->userId(), 'awaiting_date_prompt');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
