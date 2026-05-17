<?php

namespace App\Services\LineBatchCapture\State\Image;

use App\Services\LineBatchCaptureFlowService;

interface LineBatchCaptureImageStateHandler
{
    /**
     * @return string[]
     */
    public function states(): array;

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureImageContext $context): void;
}
