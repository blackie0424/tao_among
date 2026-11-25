<?php

namespace App\Services\LineBatchCapture\Actions;

use App\Contracts\CaptureSessionServiceInterface;
use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use App\Services\LineBatchCapture\LineBatchCaptureStateStore;
use App\Services\LineBatchCapture\Results\ConfirmLineBatchCaptureResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmLineBatchCaptureAction
{
    private const SESSION_FIELDS = ['tribe', 'location', 'capture_method', 'capture_date'];

    public function __construct(
        private readonly LineBatchCaptureStateStore $lineBatchCaptureStateStore,
        private readonly CaptureRecordBatchService $captureRecordBatchService,
        private readonly ?CaptureSessionServiceInterface $captureSessionService = null,
    ) {}

    public function execute(string $userId): ConfirmLineBatchCaptureResult
    {
        $fish = Fish::find($this->lineBatchCaptureStateStore->getFishId($userId));

        if (! $fish) {
            $this->lineBatchCaptureStateStore->clear($userId);

            return ConfirmLineBatchCaptureResult::failure('❌ 魚類資料已不存在，請重新操作。');
        }

        $images = $this->lineBatchCaptureStateStore->getImages($userId);
        $form = $this->lineBatchCaptureStateStore->getForm($userId);

        try {
            $outcome = filled($form['session_id'] ?? null)
                ? $this->createFromSelectedSession($userId, $fish, $images, $form)
                : $this->createFromManualFields($fish, $images, $form);

            if ($outcome instanceof ConfirmLineBatchCaptureResult) {
                return $outcome;
            }

            $this->lineBatchCaptureStateStore->clear($userId);

            return ConfirmLineBatchCaptureResult::success(count($outcome));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? '❌ 捕獲紀錄資料有誤，請重新確認。';

            return ConfirmLineBatchCaptureResult::failure($message);
        }
    }

    /**
     * 重新讀取情境、比對、寫入在同一個交易並鎖住情境，確認前被改或被刪不會被寫進去。
     *
     * @param  string[]  $images
     * @return array<int, mixed>|ConfirmLineBatchCaptureResult 新增的紀錄，或需要使用者重新選擇／確認的結果
     */
    private function createFromSelectedSession(string $userId, Fish $fish, array $images, array $form): array|ConfirmLineBatchCaptureResult
    {
        $sessionId = (int) $form['session_id'];

        return DB::transaction(function () use ($userId, $fish, $images, $form, $sessionId) {
            $attributes = $this->captureSessionService()->findAttributes($sessionId, true);

            if ($attributes === null) {
                $this->lineBatchCaptureStateStore->putForm($userId, array_diff_key($form, ['session_id' => true]));

                return ConfirmLineBatchCaptureResult::sessionGone();
            }

            if ($this->sessionFieldsDiffer($attributes, $form)) {
                $this->lineBatchCaptureStateStore->updateForm($userId, ['session_id' => $sessionId, ...$attributes]);

                return ConfirmLineBatchCaptureResult::sessionChanged();
            }

            return $this->captureRecordBatchService->createForFishFromSession(
                $fish,
                $images,
                $sessionId,
                null,
                $form['notes'] ?? null
            );
        });
    }

    /**
     * 手動填寫（含部署前舊卡片的四欄）：沿用網頁「新增情境」的規則建立或沿用情境，再寫入紀錄。
     *
     * @param  string[]  $images
     * @return array<int, mixed>
     */
    private function createFromManualFields(Fish $fish, array $images, array $form): array
    {
        $validated = $this->captureRecordBatchService->validateLineSessionData($form);

        return DB::transaction(function () use ($fish, $images, $validated) {
            $created = $this->captureSessionService()->create([
                'capture_date' => $validated['capture_date'],
                'tribe' => $validated['tribe'],
                'capture_method' => $validated['capture_method'],
                'place_name' => $validated['location'] ?? null,
            ]);

            return $this->captureRecordBatchService->createForFishFromSession(
                $fish,
                $images,
                $created['session']->id,
                null,
                $validated['notes'] ?? null
            );
        });
    }

    private function sessionFieldsDiffer(array $attributes, array $form): bool
    {
        foreach (self::SESSION_FIELDS as $field) {
            if ((string) ($attributes[$field] ?? '') !== (string) ($form[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function captureSessionService(): CaptureSessionServiceInterface
    {
        return $this->captureSessionService ?? app(CaptureSessionServiceInterface::class);
    }
}
