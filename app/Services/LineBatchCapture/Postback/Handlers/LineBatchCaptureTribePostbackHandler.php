<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureTribePostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'open_batch_capture_tribe_selector',
            'select_batch_capture_tribe',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'open_batch_capture_tribe_selector') {
            if ($flow->getImages($context->userId()) === []) {
                $flow->replySummary($context->replyToken(), $context->userId());
                return;
            }

            $flow->putState($context->userId(), 'waiting_tribe_selection');
            $flow->replyTribeSelectionCard($context->replyToken());
            return;
        }

        $tribe = $context->params()['tribe'] ?? null;
        if (!$tribe || !in_array($tribe, config('fish_options.tribes', []), true)) {
            $flow->replyTribeSelectionCard($context->replyToken(), '❌ 部落資料無效，請重新選擇。');
            return;
        }

        $flow->updateForm($context->userId(), ['tribe' => $tribe]);
        $flow->putState($context->userId(), 'awaiting_location_prompt');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
