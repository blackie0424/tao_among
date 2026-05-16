<?php

namespace App\Services\LineBatchCapture\State\Text;

use App\Services\LineBatchCaptureFlowService;

interface LineBatchCaptureTextStateHandler
{
    /**
     * @return string[]
     */
    public function states(): array;

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureTextContext $context): void;
}
