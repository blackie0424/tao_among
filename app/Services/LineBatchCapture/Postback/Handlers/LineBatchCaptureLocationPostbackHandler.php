<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureLocationPostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return ['prompt_batch_capture_location', 'skip_batch_capture_location'];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        $form = $flow->getForm($context->userId());
        if (empty($form['tribe'])) {
            $flow->replySummary($context->replyToken(), $context->userId());

            return;
        }

        if ($context->action() === 'skip_batch_capture_location') {
            if ($flow->allowsEmptyLocation()) {
                $flow->updateForm($context->userId(), ['location' => '']);
                $flow->putState($context->userId(), 'awaiting_method_prompt');
            }
            $flow->replySummary($context->replyToken(), $context->userId());

            return;
        }

        $flow->putState($context->userId(), 'awaiting_location_input');
        $flow->replyText($context->replyToken(), '請輸入捕獲地點：');
    }
}
