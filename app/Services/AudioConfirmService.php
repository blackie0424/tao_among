<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 網頁錄音的「確認」步驟（做法同 #157 的叫法錄音）：
 * 瀏覽器已把音檔 PUT 到登入者專屬的暫存資料夾，這裡驗證暫存檔、複製到正式資料夾、同一交易寫入紀錄；
 * 失敗就回滾並刪掉已複製的正式檔。暫存檔留著，由 bucket 的生命週期規則清理。
 *
 * 一個暫存檔只能確認成一筆紀錄：以資料庫唯一欄位 upload_key（<使用者編號>:<暫存檔名>）保證，
 * 所以並發重送與跨魚重用都由資料庫擋下；正式檔名沿用暫存檔名（UUID），不會有兩筆紀錄共用同一個正式檔。
 * 呼叫端不要再包外層交易：複製檔案後的失敗補償只涵蓋這裡的交易。
 */
class AudioConfirmService
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly FishAudioRecordingService $recordings,
    ) {}

    /** 新增一段錄音。同一個暫存檔重送回傳同一筆；用在別隻魚的紀錄上則拒絕。 */
    public function confirm(Fish $fish, int $userId, string $pendingFilename, ?string $spokenName, string $tribe, int $durationMs): FishAudio
    {
        // 名稱與部落不合法就在複製檔案之前拒絕
        $spokenName = $this->recordings->resolveSpokenName($fish, $spokenName);

        return $this->confirmOnce(
            $userId,
            $pendingFilename,
            fn (string $key) => $this->recordings->create($fish, $spokenName, $tribe, $pendingFilename, $durationMs, $userId, $key),
            fn (FishAudio $existing) => $existing->fish_id === $fish->id,
        );
    }

    /** 換音檔並同時更新部落（同一個交易，失敗時一起回滾並刪掉已複製的檔）。 */
    public function replace(FishAudio $audio, int $userId, string $pendingFilename, int $durationMs, string $tribe): FishAudio
    {
        return $this->confirmOnce(
            $userId,
            $pendingFilename,
            fn (string $key) => $this->recordings->updateTribe(
                $this->recordings->replaceFile($audio, $pendingFilename, $durationMs, $key),
                $tribe
            ),
            fn (FishAudio $existing) => $existing->id === $audio->id,
        );
    }

    /**
     * @param  callable(string): FishAudio  $persist  寫入（收到 upload_key）
     * @param  callable(FishAudio): bool  $isSameTarget  已存在的紀錄是不是這次要確認的對象
     */
    private function confirmOnce(int $userId, string $filename, callable $persist, callable $isSameTarget): FishAudio
    {
        $key = $userId.':'.$filename;

        if ($existing = FishAudio::where('upload_key', $key)->first()) {
            return $this->sameTargetOrReject($existing, $isSameTarget);
        }

        $pendingPath = FishAudio::pendingPath($userId, $filename);
        $size = $this->storage->fileSize($pendingPath);
        if ($size === null || $size > FishAudio::MAX_BYTES) {
            throw ValidationException::withMessages(['recording' => '找不到錄音檔或檔案太大，請重新錄音']);
        }

        $finalPath = $this->storage->getAudioFolder().'/'.$filename;
        if (! $this->storage->copyObject($pendingPath, $finalPath)) {
            throw ValidationException::withMessages(['recording' => '錄音儲存失敗，請重試']);
        }

        try {
            return DB::transaction(fn () => $persist($key));
        } catch (UniqueConstraintViolationException $e) {
            // 同一個暫存檔被另一個請求搶先確認了：正式檔屬於那一筆，這裡不能刪
            $winner = FishAudio::withTrashed()->where('upload_key', $key)->first();
            if (! $winner || $winner->trashed()) {
                throw ValidationException::withMessages(['recording' => '這段錄音已使用過，請重新錄音']);
            }

            return $this->sameTargetOrReject($winner, $isSameTarget);
        } catch (Throwable $e) {
            if (! $this->storage->delete($finalPath)) {
                Log::warning('[audio.confirm] 補償刪除正式檔失敗', ['path' => $finalPath]);
            }
            throw $e;
        }
    }

    private function sameTargetOrReject(FishAudio $existing, callable $isSameTarget): FishAudio
    {
        if (! $isSameTarget($existing)) {
            throw ValidationException::withMessages(['recording' => '這段錄音已用在別的紀錄，請重新錄音']);
        }

        return $existing;
    }
}
