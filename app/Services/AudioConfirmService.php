<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 網頁錄音的「確認」步驟（做法同 #157 的叫法錄音）：
 * 瀏覽器已把音檔 PUT 到登入者專屬的暫存資料夾，這裡驗證暫存檔、複製到正式資料夾、同一交易寫入紀錄；
 * 失敗就回滾並刪掉已複製的正式檔。暫存檔留著，由 bucket 的生命週期規則清理。
 * 正式檔名沿用暫存檔名（UUID），所以同一個暫存檔重送確認不會產生兩筆。
 */
class AudioConfirmService
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly FishAudioRecordingService $recordings,
    ) {}

    /** 新增一段錄音。 */
    public function confirm(Fish $fish, int $userId, string $pendingFilename, ?string $spokenName, string $tribe, int $durationMs): FishAudio
    {
        // 名稱與部落不合法就在複製檔案之前拒絕
        $spokenName = $this->recordings->resolveSpokenName($fish, $spokenName);

        $existing = FishAudio::where('fish_id', $fish->id)->where('name', $pendingFilename)->first();
        if ($existing) {
            return $existing;
        }

        return $this->copyThen($userId, $pendingFilename, fn () => $this->recordings->create(
            $fish, $spokenName, $tribe, $pendingFilename, $durationMs, $userId
        ));
    }

    /** 換音檔（錄音者與名稱不變）。 */
    public function replace(FishAudio $audio, int $userId, string $pendingFilename, int $durationMs): FishAudio
    {
        if ($audio->name === $pendingFilename) {
            return $audio;
        }

        return $this->copyThen($userId, $pendingFilename, fn () => $this->recordings->replaceFile($audio, $pendingFilename, $durationMs));
    }

    /** 驗證暫存檔並複製到正式資料夾，再執行寫入；寫入失敗就刪掉剛複製的正式檔。 */
    private function copyThen(int $userId, string $filename, callable $persist): FishAudio
    {
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
            return DB::transaction($persist);
        } catch (Throwable $e) {
            if (! $this->storage->delete($finalPath)) {
                Log::warning('[audio.confirm] 補償刪除正式檔失敗', ['path' => $finalPath]);
            }
            throw $e;
        }
    }
}
