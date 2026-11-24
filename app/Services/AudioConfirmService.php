<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishAudioUpload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 網頁錄音的「確認」步驟（做法同 #157 的叫法錄音）：
 * 瀏覽器已把音檔 PUT 到登入者專屬的暫存資料夾，這裡驗證暫存檔、複製到正式資料夾、同一交易寫入紀錄；
 * 失敗就回滾並刪掉自己複製的正式檔。暫存檔留著，由 bucket 的生命週期規則清理。
 *
 * - 每次確認都複製成新的 UUID 檔名：正式檔只屬於當次請求，補償只會刪自己的檔，不會牽連別的請求或紀錄。
 * - 一個暫存檔只能確認成一筆紀錄：已消耗的暫存檔記在 fish_audio_uploads（唯一約束、換檔與軟刪除後仍在）。
 *   複製前先查（同一對象的重送回傳同一筆；用在別隻魚、或紀錄已被刪除則直接拒絕，不複製）；
 *   查到之後才並發搶進來的，由唯一約束擋下，輸的那個刪掉自己複製的檔。
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
            fn (string $finalName) => $this->recordings->create($fish, $spokenName, $tribe, $finalName, $durationMs, $userId),
            fn (FishAudio $existing) => $existing->fish_id === $fish->id,
        );
    }

    /** 換音檔並同時更新部落（同一個交易，失敗時一起回滾並刪掉已複製的檔）。 */
    public function replace(FishAudio $audio, int $userId, string $pendingFilename, int $durationMs, string $tribe): FishAudio
    {
        return $this->confirmOnce(
            $userId,
            $pendingFilename,
            fn (string $finalName) => $this->recordings->updateTribe(
                $this->recordings->replaceFile($audio, $finalName, $durationMs),
                $tribe
            ),
            fn (FishAudio $existing) => $existing->id === $audio->id,
        );
    }

    /**
     * @param  callable(string): FishAudio  $persist  寫入（收到正式檔名）
     * @param  callable(FishAudio): bool  $isSameTarget  已消耗這個暫存檔的紀錄，是不是這次要確認的對象
     */
    private function confirmOnce(int $userId, string $pendingFilename, callable $persist, callable $isSameTarget): FishAudio
    {
        $key = FishAudioUpload::keyFor($userId, $pendingFilename);

        if ($used = FishAudioUpload::where('upload_key', $key)->first()) {
            return $this->resolveUsed($used, $isSameTarget);
        }

        $pendingPath = FishAudio::pendingPath($userId, $pendingFilename);
        $size = $this->storage->fileSize($pendingPath);
        if ($size === null || $size > FishAudio::MAX_BYTES) {
            throw ValidationException::withMessages(['recording' => '找不到錄音檔或檔案太大，請重新錄音']);
        }

        $finalName = Str::uuid()->toString().'.'.pathinfo($pendingFilename, PATHINFO_EXTENSION);
        $finalPath = $this->storage->getAudioFolder().'/'.$finalName;
        if (! $this->storage->copyObject($pendingPath, $finalPath)) {
            throw ValidationException::withMessages(['recording' => '錄音儲存失敗，請重試']);
        }

        try {
            return DB::transaction(function () use ($persist, $finalName, $key) {
                $audio = $persist($finalName);
                FishAudioUpload::create(['upload_key' => $key, 'fish_audio_id' => $audio->id]);

                return $audio;
            });
        } catch (Throwable $e) {
            $this->deleteOwnCopy($finalPath);

            if ($e instanceof UniqueConstraintViolationException && ($used = FishAudioUpload::where('upload_key', $key)->first())) {
                // 查到之後才有別的請求搶先確認了同一個暫存檔：回傳搶贏的那一筆
                return $this->resolveUsed($used, $isSameTarget);
            }
            throw $e;
        }
    }

    private function resolveUsed(FishAudioUpload $used, callable $isSameTarget): FishAudio
    {
        $existing = FishAudio::find($used->fish_audio_id); // 軟刪除的找不到
        if (! $existing) {
            throw ValidationException::withMessages(['recording' => '這段錄音已使用過，請重新錄音']);
        }
        if (! $isSameTarget($existing)) {
            throw ValidationException::withMessages(['recording' => '這段錄音已用在別的紀錄，請重新錄音']);
        }

        return $existing;
    }

    private function deleteOwnCopy(string $finalPath): void
    {
        if (! $this->storage->delete($finalPath)) {
            Log::warning('[audio.confirm] 補償刪除正式檔失敗', ['path' => $finalPath]);
        }
    }
}
