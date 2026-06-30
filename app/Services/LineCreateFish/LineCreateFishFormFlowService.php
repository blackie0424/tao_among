<?php

namespace App\Services\LineCreateFish;

use App\Contracts\LineMessagingClientInterface;
use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCaptureFlowService;
use App\Services\LineBatchCaptureMessageBuilder;
use App\Services\LineUploadService;
use Illuminate\Validation\ValidationException;

class LineCreateFishFormFlowService extends LineBatchCaptureFlowService
{
    public function __construct(
        LineMessagingClientInterface $lineMessagingClient,
        CaptureRecordBatchService $captureRecordBatchService,
        LineBatchCaptureMessageBuilder $lineBatchCaptureMessageBuilder,
        ?LineUploadService $lineUploadService = null,
    ) {
        parent::__construct(
            lineMessagingClient: $lineMessagingClient,
            captureRecordBatchService: $captureRecordBatchService,
            lineBatchCaptureMessageBuilder: $lineBatchCaptureMessageBuilder,
            lineUploadService: $lineUploadService,
            lineBatchCaptureStateStore: new LineCreateFishFormStateStore(),
        );
    }

    /**
     * @param string[] $images
     */
    public function startFormSession(string $userId, string $replyToken, int $fishId, array $images): void
    {
        $store = $this->lineBatchCaptureStateStore();
        $store->putFishId($userId, $fishId);
        $store->putImages($userId, $images);
        $store->putForm($userId, []);

        $this->replySessionPickerOrTribeCard($replyToken, $userId);
    }

    public function confirmCapture(string $userId, string $replyToken): void
    {
        $store = $this->lineBatchCaptureStateStore();
        $fish = Fish::find($store->getFishId($userId));

        if (!$fish) {
            $store->clear($userId);
            $this->replyText($replyToken, '❌ 魚類資料已不存在，請重新操作。');
            return;
        }

        try {
            $records = $this->captureRecordBatchService()->createForFish(
                $fish,
                $store->getImages($userId),
                $store->getForm($userId),
            );

            if (!empty($records) && !$fish->display_capture_record_id) {
                $fish->update(['display_capture_record_id' => $records[0]->id]);
            }

            $store->clear($userId);
            $this->replyText($replyToken, "✅ 成功新增魚類「{$fish->name}」。");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? '❌ 捕獲紀錄資料有誤，請重新確認。';
            $this->replyText($replyToken, $message);
        }
    }

    public function handlePostback(string $userId, string $replyToken, string $action, array $params): bool
    {
        if ($action === 'cancel_batch_capture_record') {
            $this->clearState($userId);
            $this->replyText($replyToken, '✅ 已取消新增魚類');
            return true;
        }

        if ($action === 'reset_batch_capture_form') {
            $this->putState($userId, 'waiting_tribe_selection');
            $this->putForm($userId, []);
            $this->replyTribeSelectionCard($replyToken, '重新填寫：請選擇部落');
            return true;
        }

        return parent::handlePostback($userId, $replyToken, $action, $params);
    }

    public function clearState(string $userId): void
    {
        $fishId = $this->lineBatchCaptureStateStore()->getFishId($userId);
        parent::clearState($userId);

        if ($fishId) {
            $fish = Fish::find($fishId);
            if ($fish && $fish->captureRecords()->doesntExist()) {
                $fish->forceDelete();
            }
        }
    }

    public function lineBatchCaptureStateStore(): LineBatchCaptureStateStore
    {
        return parent::lineBatchCaptureStateStore();
    }
}
