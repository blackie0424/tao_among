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

            if ($context->imageSet() !== null) {
                $this->handleWithImageSet($flow, $context, $filename, $maxImages);
            } else {
                $images[] = $filename;
                $flow->putImages($context->userId(), $images);
                $flow->putState($context->userId(), 'waiting_images');
                $flow->replySummary($context->replyToken(), $context->userId());
            }
        } catch (\Exception $e) {
            Log::error('LINE Bot batch capture image upload failed', [
                'userId' => $context->userId(),
                'error'  => $e->getMessage(),
            ]);

            $flow->replyText($context->replyToken(), '❌ 捕獲照片處理失敗，請稍後再試。');
        }
    }

    private function handleWithImageSet(
        LineBatchCaptureFlowService $flow,
        LineBatchCaptureImageContext $context,
        string $filename,
        int $maxImages,
    ): void {
        $imageSet = $context->imageSet();
        $store    = $flow->lineBatchCaptureStateStore();
        $userId   = $context->userId();

        $existing = $store->getIndexedImages($userId);
        $indexed  = ($existing !== null && $existing[0] === $imageSet->id()) ? $existing[1] : [];
        $indexed[$imageSet->index()] = $filename;

        $allImages   = $flow->getImages($userId);
        $totalNeeded = $imageSet->total();
        $received    = count($indexed);

        if ($received >= $totalNeeded || count($allImages) + $received >= $maxImages) {
            ksort($indexed);
            $newImages = array_merge($allImages, array_values($indexed));
            $flow->putImages($userId, array_slice($newImages, 0, $maxImages));
            $store->forgetIndexedImages($userId);
            $flow->putState($userId, 'waiting_tribe_selection');
            $flow->replyTribeSelectionCard($context->replyToken(), "✅ 已收到 {$received} 張圖片，繼續填寫捕獲資訊。");
        } else {
            $store->putIndexedImages($userId, [$imageSet->id(), $indexed, $totalNeeded]);
            $flow->putState($userId, 'waiting_images');
            $flow->replySummary($context->replyToken(), $userId);
        }
    }
}
