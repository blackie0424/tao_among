<?php

namespace App\Services\LineBatchCapture\State\Image\Handlers;

use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageStateHandler;
use App\Services\LineBatchCaptureFlowService;

class LineBatchCaptureLockedImageStateHandler implements LineBatchCaptureImageStateHandler
{
    public function states(): array
    {
        return [
            'waiting_tribe_selection',
            'awaiting_location_prompt',
            'awaiting_location_input',
            'awaiting_method_prompt',
            'waiting_method_selection',
            'awaiting_date_prompt',
            'waiting_date_selection',
            'awaiting_date_manual_input',
            'awaiting_notes_prompt',
            'awaiting_notes_input',
            'waiting_confirm',
        ];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureImageContext $context): void
    {
        $flow->replyText($context->replyToken(), '目前已進入欄位填寫流程，請先完成部落、地點與捕獲方式等資料。');
    }
}
