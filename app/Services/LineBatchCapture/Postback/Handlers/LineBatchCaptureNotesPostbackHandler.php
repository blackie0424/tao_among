<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureNotesPostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'prompt_batch_capture_notes',
            'skip_batch_capture_notes',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'prompt_batch_capture_notes') {
            $form = $flow->getForm($context->userId());
            if (empty($form['capture_date'])) {
                $flow->replySummary($context->replyToken(), $context->userId());
                return;
            }

            $flow->putState($context->userId(), 'awaiting_notes_input');
            $flow->replyText($context->replyToken(), '請輸入備註，若沒有可直接輸入或點選略過。');
            return;
        }

        $flow->updateForm($context->userId(), ['notes' => null]);
        $flow->putState($context->userId(), 'waiting_confirm');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
