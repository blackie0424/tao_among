<?php

namespace App\Services\LineBatchCapture\Postback\Handlers;

use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackContext;
use App\Services\LineBatchCapture\Postback\LineBatchCapturePostbackHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureSessionPostbackHandler implements LineBatchCapturePostbackHandler
{
    public function actions(): array
    {
        return [
            'select_capture_session',
            'skip_session_picker',
        ];
    }

    public function protectedActions(): array
    {
        return $this->actions();
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void
    {
        if ($context->action() === 'skip_session_picker') {
            $flow->putState($context->userId(), 'waiting_tribe_selection');
            $flow->replyTribeSelectionCard($context->replyToken());
            return;
        }

        $params = $context->params();
        $tribe         = $params['tribe'] ?? null;
        $location      = $params['location'] ?? null;
        $captureMethod = $params['capture_method'] ?? null;
        $captureDate   = $params['capture_date'] ?? null;

        if (!$tribe || !$location || !$captureMethod || !$captureDate) {
            $flow->replySessionPickerOrTribeCard($context->replyToken(), $context->userId());
            return;
        }

        $flow->updateForm($context->userId(), [
            'tribe'          => $tribe,
            'location'       => $location,
            'capture_method' => $captureMethod,
            'capture_date'   => $captureDate,
        ]);
        $flow->putState($context->userId(), 'waiting_confirm');
        $flow->replySummary($context->replyToken(), $context->userId());
    }
}
