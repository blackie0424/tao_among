<?php

namespace App\Services\LineBatchCapture\Actions;

use App\Models\Fish;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCapture\Results\StartLineBatchCaptureResult;
use Illuminate\Support\Facades\Cache;

class StartLineBatchCaptureAction
{
    public function __construct(
        private readonly LineBatchCaptureStateStore $lineBatchCaptureStateStore,
    ) {
    }

    public function execute(string $userId, int $fishId): StartLineBatchCaptureResult
    {
        $fish = Fish::find($fishId);

        if (!$fish) {
            return StartLineBatchCaptureResult::failure('❌ 找不到魚類資料，請重新操作。');
        }

        Cache::forget("line_user_{$userId}_create_fish_state");
        Cache::forget("line_user_{$userId}_create_fish_images");

        $this->lineBatchCaptureStateStore->clear($userId);
        $this->lineBatchCaptureStateStore->startSession($userId, $fish->id);

        return StartLineBatchCaptureResult::success($fish);
    }
}
