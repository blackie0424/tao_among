<?php

namespace App\Services\LineBatchCapture\State\Image\Handlers;

use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageContext;
use App\Services\LineBatchCapture\State\Image\LineBatchCaptureImageStateHandler;
use App\Services\LineBatchCaptureFlowService;
use Illuminate\Support\Facades\Log;

class LineBatchCaptureWaitingImagesImageStateHandler implements LineBatchCaptureImageStateHandler
{
    public function states(): array
    {
        return ['waiting_images'];
    }

    public function handle(LineBatchCaptureFlowService $flow, LineBatchCaptureImageContext $context): void
    {
        $maxImages = config('fish_options.batch_upload.max_files_mobile', 5);
        $images = $flow->getImages($context->userId());

        if (count($images) >= $maxImages) {
            $flow->replySummary($context->replyToken(), $context->userId());
            return;
        }

        try {
            $filename = $flow->uploadLineImage($context->messageId());
            $images[] = $filename;
            $flow->putImages($context->userId(), $images);
            $flow->putState($context->userId(), 'waiting_images');
            $flow->replySummary($context->replyToken(), $context->userId());
        } catch (\Exception $e) {
            Log::error('LINE Bot batch capture image upload failed', [
                'userId' => $context->userId(),
                'error' => $e->getMessage(),
            ]);

            $flow->replyText($context->replyToken(), '❌ 捕獲照片處理失敗，請稍後再試。');
        }
    }
}
