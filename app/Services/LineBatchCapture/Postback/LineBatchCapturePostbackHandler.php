<?php

namespace App\Services\LineBatchCapture\Postback;

use App\Services\LineBatchCaptureFlowService;

interface LineBatchCapturePostbackHandler
{
    /**
     * @return string[]
     */
    public function actions(): array;

    /**
     * @return string[]
     */
    public function protectedActions(): array;

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCapturePostbackContext $context): void;
}
