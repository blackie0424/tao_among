<?php

namespace App\Services\LineBatchCapture\Actions;

use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCapture\Results\ConfirmLineBatchCaptureResult;
use Illuminate\Validation\ValidationException;

class ConfirmLineBatchCaptureAction
{
    public function __construct(
        private readonly LineBatchCaptureStateStore $lineBatchCaptureStateStore,
        private readonly CaptureRecordBatchService $captureRecordBatchService,
    ) {
    }

    public function execute(string $userId): ConfirmLineBatchCaptureResult
    {
        $fish = Fish::find($this->lineBatchCaptureStateStore->getFishId($userId));

        if (!$fish) {
            $this->lineBatchCaptureStateStore->clear($userId);

            return ConfirmLineBatchCaptureResult::failure('❌ 魚類資料已不存在，請重新操作。');
        }

        try {
            $records = $this->captureRecordBatchService->createForFish(
                $fish,
                $this->lineBatchCaptureStateStore->getImages($userId),
                $this->lineBatchCaptureStateStore->getForm($userId)
            );

            $this->lineBatchCaptureStateStore->clear($userId);

            return ConfirmLineBatchCaptureResult::success(count($records));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? '❌ 捕獲紀錄資料有誤，請重新確認。';

            return ConfirmLineBatchCaptureResult::failure($message);
        }
    }
}
