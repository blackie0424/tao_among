<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 發音錄音的寫入與整理（網頁與 LINE 共用）。
 * fish_audios：name＝檔名、locate＝部落、spoken_name＝對應魚名拆出的哪個名稱。
 */
class FishAudioRecordingService
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly FishNameSplitter $splitter,
    ) {}

    /**
     * 新增一段錄音；同名稱同部落已有紀錄時也是新增一筆，不覆蓋。
     * $spokenName 為 null（LINE 在選名稱流程上線前的過渡）：單一名稱的魚自動帶入該名稱，多名稱的魚留空（歸未對應名稱）。
     * 網頁新增必填名稱，由呼叫端驗證後傳入。
     * 主要發音設定維持現狀：新增時指向新檔（是否改變見 #174）。
     */
    public function create(Fish $fish, ?string $spokenName, string $tribe, string $filename, ?int $durationMs, ?int $recordedBy): FishAudio
    {
        $spokenName = $this->resolveSpokenName($fish, $spokenName);
        $this->assertTribe($tribe);

        return DB::transaction(function () use ($fish, $spokenName, $tribe, $filename, $durationMs, $recordedBy) {
            $audio = FishAudio::create([
                'fish_id' => $fish->id,
                'name' => $filename,
                'locate' => $tribe,
                'spoken_name' => $spokenName,
                'duration' => $durationMs,
                'recorded_by' => $recordedBy,
            ]);
            $fish->update(['audio_filename' => $filename]);

            return $audio;
        });
    }

    /**
     * 換音檔：換的是主要發音檔時，指標同交易改為新檔名。
     * 舊檔等最外層交易提交後才刪；外層回滾時資料仍指向舊檔，舊檔要還在。
     */
    public function replaceFile(FishAudio $audio, string $newFilename, ?int $durationMs): FishAudio
    {
        $oldFilename = $audio->name;

        DB::transaction(function () use ($audio, $newFilename, $durationMs, $oldFilename) {
            $audio->update(['name' => $newFilename, 'duration' => $durationMs]);
            Fish::where('id', $audio->fish_id)
                ->where('audio_filename', $oldFilename)
                ->update(['audio_filename' => $newFilename]);
        });

        if ($oldFilename !== $newFilename) {
            DB::afterCommit(fn () => $this->storage->delete($this->storage->getAudioFolder().'/'.$oldFilename));
        }

        return $audio->fresh();
    }

    public function updateTribe(FishAudio $audio, string $tribe): FishAudio
    {
        $this->assertTribe($tribe);
        $audio->update(['locate' => $tribe]);

        return $audio->fresh();
    }

    /** 指派（或改派）到該魚目前存在的名稱。 */
    public function assignName(FishAudio $audio, string $spokenName): FishAudio
    {
        $this->assertNameBelongsToFish($audio->fish, $spokenName);
        $audio->update(['spoken_name' => $spokenName]);

        return $audio->fresh();
    }

    /**
     * 軟刪除一段錄音（音檔由 FishAudio 的 deleting 事件刪除）。
     * 刪的是主要發音那段時，指標改指向剩餘最新一段，沒有就清空。
     */
    public function delete(FishAudio $audio): void
    {
        DB::transaction(function () use ($audio) {
            $fish = $audio->fish;
            $wasMain = $fish && $fish->audio_filename === $audio->name;

            $audio->delete();

            if ($wasMain) {
                $latest = FishAudio::where('fish_id', $fish->id)->orderByDesc('id')->first();
                $fish->update(['audio_filename' => $latest?->name]);
            }
        });
    }

    /**
     * 依名稱分組：沒有錄音的名稱也列出；名稱欄為空或不在目前魚名內的歸「未對應名稱」。
     * 未對應是讀取時算出來的，魚改名再改回同字串會自動歸回。
     *
     * @return array{groups: array<string, Collection<int, FishAudio>>, unassigned: Collection<int, FishAudio>}
     */
    public function groupByName(Fish $fish): array
    {
        $names = $this->splitter->split($fish->name);
        $audios = FishAudio::where('fish_id', $fish->id)->orderBy('id')->get();

        $groups = [];
        foreach ($names as $name) {
            $groups[$name] = $audios->filter(fn (FishAudio $a) => $a->spoken_name === $name)->values();
        }

        return [
            'groups' => $groups,
            'unassigned' => $audios->filter(fn (FishAudio $a) => ! in_array($a->spoken_name, $names, true))->values(),
        ];
    }

    /** 名稱檢查與補齊（見 create 的說明）；不合法就丟 InvalidArgumentException。 */
    public function resolveSpokenName(Fish $fish, ?string $spokenName): ?string
    {
        $names = $this->splitter->split($fish->name);
        if ($names === []) {
            throw new InvalidArgumentException('這隻魚還沒有名稱');
        }
        if ($spokenName === null) {
            return count($names) === 1 ? $names[0] : null;
        }
        $this->assertNameBelongsToFish($fish, $spokenName);

        return $spokenName;
    }

    private function assertNameBelongsToFish(Fish $fish, string $spokenName): void
    {
        if (! in_array($spokenName, $this->splitter->split($fish->name), true)) {
            throw new InvalidArgumentException('名稱不在這隻魚的名稱內');
        }
    }

    private function assertTribe(string $tribe): void
    {
        if (! in_array($tribe, config('fish_options.tribes', []), true)) {
            throw new InvalidArgumentException('部落不在可選的部落內');
        }
    }
}
