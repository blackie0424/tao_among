<?php

namespace App\Models;

use App\Contracts\StorageServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 使用者對某條魚「這是哪個部落的說法」的回答。
 * 與正式資料（魚名、部落分類、發音）完全分開，不回寫。
 */
class FishTribeResponse extends Model
{
    public const NAME_MAX_LENGTH = 60;

    /** 錄音上傳網址的有效秒數 */
    public const RECORDING_UPLOAD_TTL_SECONDS = 300;

    /** 錄音檔可用的副檔名（audio/mp4 存成 m4a、audio/aac 存成 aac） */
    public const RECORDING_EXTENSIONS = ['m4a', 'aac'];

    /** 錄音最長秒數（前後端共用，由 formOptions 傳給前端） */
    public const RECORDING_MAX_SECONDS = 30;

    /** 錄音檔大小上限（位元組）：2 MB */
    public const RECORDING_MAX_BYTES = 2 * 1024 * 1024;

    private const DEADLOCK_RETRY_ATTEMPTS = 3;

    public const EMPTY_RESPONSE_MESSAGE = '叫法、分類、錄音至少填一項';

    /** 魚名為此值代表尚未命名，不列為叫法選項 */
    public const UNNAMED_FISH_NAME = '我不知道';

    /** 叫法要有這麼多人填，才可能標「多數」 */
    private const MAJORITY_MIN_RESPONDERS = 3;

    /** 魚島魚卡上列出的回答者人數（其餘以「等 N 人」表示） */
    private const ISLAND_CARD_RESPONDERS = 2;

    protected $fillable = ['user_id', 'fish_id', 'tribe', 'name', 'food_category', 'name_submitted_at', 'recording_filename', 'recording_submitted_at', 'recording_seconds'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class);
    }

    /** 還沒送出的錄音暫存路徑：放在該使用者專屬的資料夾，別人的檔名在自己的資料夾找不到。 */
    public static function pendingRecordingPath(int $userId, string $filename): string
    {
        return config('storage.drivers.s3.folders.response_audio_pending').'/'.$userId.'/'.$filename;
    }

    /** 已送出的錄音在正式資料夾的路徑。 */
    public static function recordingPath(string $filename): string
    {
        return config('storage.drivers.s3.folders.response_audio').'/'.$filename;
    }

    /** 暫存檔名的格式：UUID 加白名單副檔名，不含任何路徑。 */
    public static function recordingFilenamePattern(): string
    {
        return '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.('.implode('|', self::RECORDING_EXTENSIONS).')$/';
    }

    private static function recordingUrl(string $filename): string
    {
        return app(StorageServiceInterface::class)->getUrl('response_audio', $filename);
    }

    /** 微秒精度的時間字串（固定寬度，可直接字串排序），用來區分同一秒內的先後。 */
    public static function submissionTime(): string
    {
        return now()->format('Y-m-d H:i:s.u');
    }

    /**
     * 送出（或修改）使用者對這條魚、這個部落的說法；同一人同魚同部落只會有一筆，後到者的值生效。
     *
     * 先直接新增（INSERT IGNORE，自動提交、不持有鎖）；已存在才在交易內鎖住該列再判斷與更新。
     * 不先對「還不存在的鍵」做 SELECT ... FOR UPDATE，否則 InnoDB 下兩個請求會各自持有間隙鎖再同時新增而死鎖。
     * 叫法只是大小寫或前後空白不同（同一種寫法）時，保留原本的寫法與送出時間；
     * 換成不同寫法才記為重新送出。
     * 錄音不適用「略過視為清空」：沒帶新錄音就保留舊的，「至少一項」以保留後的最終狀態判斷。
     *
     * @param  string|null  $recording  已複製到正式資料夾的新錄音檔名
     * @param  int|null  $recordingSeconds  新錄音的秒數（有新錄音時才有值）
     * @return string|null 被新錄音取代的舊錄音檔名（呼叫端在提交後刪除）
     *
     * @throws ValidationException 保留後叫法、分類、錄音皆空
     */
    public static function submit(array $keys, ?string $name, ?string $foodCategory, ?string $recording = null, ?int $recordingSeconds = null): ?string
    {
        $hasNewContent = $name !== null || $foodCategory !== null || $recording !== null;
        $newRow = [
            ...$keys,
            'name' => $name,
            'food_category' => $foodCategory,
            'name_submitted_at' => $name === null ? null : self::submissionTime(),
            'recording_filename' => $recording,
            'recording_submitted_at' => $recording === null ? null : self::submissionTime(),
            'recording_seconds' => $recording === null ? null : $recordingSeconds,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($hasNewContent && self::insertOrIgnore([$newRow]) === 1) {
            return null;
        }

        // 偶發的死鎖由 Laravel 重試
        return DB::transaction(function () use ($keys, $name, $foodCategory, $recording, $recordingSeconds, $hasNewContent, $newRow) {
            $row = self::where($keys)->lockForUpdate()->first();
            // 先前的新增因為已存在而被略過，但那一筆在這之間被刪除（例如作者刪掉只有錄音的回答）：照鎖的先後，現在重新新增
            if ($row === null && $hasNewContent) {
                self::insert($newRow);

                return null;
            }
            if ($row === null || (! $hasNewContent && $row->recording_filename === null)) {
                throw ValidationException::withMessages(['name' => self::EMPTY_RESPONSE_MESSAGE]);
            }
            $replaced = $recording === null ? null : $row->recording_filename;
            $row->fill(['food_category' => $foodCategory, ...self::nameAttributes($row, $name), ...self::recordingAttributes($recording, $recordingSeconds)])->save();

            return $replaced;
        }, attempts: self::DEADLOCK_RETRY_ATTEMPTS);
    }

    /**
     * 刪除作者自己在某部落的錄音。先刪檔案，成功才改資料表（失敗時資料表不變，可重試）；
     * 刪後叫法、分類、錄音皆空就連整筆回答一起刪。
     *
     * @param  callable(string): bool  $deleteFile  刪除正式資料夾裡指定檔名的檔案
     * @return bool 檔案刪除失敗時為 false
     */
    public static function removeRecording(array $keys, callable $deleteFile): bool
    {
        return DB::transaction(function () use ($keys, $deleteFile) {
            $row = self::where($keys)->lockForUpdate()->first();
            if ($row === null || $row->recording_filename === null) {
                return true;
            }
            if (! $deleteFile($row->recording_filename)) {
                return false;
            }
            if ($row->name === null && $row->food_category === null) {
                $row->delete();
            } else {
                $row->update(['recording_filename' => null, 'recording_submitted_at' => null, 'recording_seconds' => null]);
            }

            return true;
        }, attempts: self::DEADLOCK_RETRY_ATTEMPTS);
    }

    private static function recordingAttributes(?string $recording, ?int $recordingSeconds): array
    {
        return $recording === null ? [] : [
            'recording_filename' => $recording,
            'recording_submitted_at' => self::submissionTime(),
            'recording_seconds' => $recordingSeconds,
        ];
    }

    private static function nameAttributes(self $row, ?string $name): array
    {
        if ($name === null) {
            return ['name' => null, 'name_submitted_at' => null];
        }
        if ($row->name !== null && self::sameSpelling($row->name, $name)) {
            return [];
        }

        return ['name' => $name, 'name_submitted_at' => self::submissionTime()];
    }

    /** @return list<string> */
    public static function selectableFoodCategories(): array
    {
        return config('fish_options.response_food_categories');
    }

    /** @return array{tribes: list<string>, food_categories: list<string>, name_options: list<string>, name_max_length: int, recording_max_seconds: int} */
    public static function formOptions(string $fishName): array
    {
        return [
            'tribes' => config('fish_options.tribes'),
            'food_categories' => self::selectableFoodCategories(),
            'name_options' => $fishName === self::UNNAMED_FISH_NAME ? [] : [$fishName],
            'name_max_length' => self::NAME_MAX_LENGTH,
            'recording_max_seconds' => self::RECORDING_MAX_SECONDS,
        ];
    }

    /** 目前使用者對這條魚已送出的回答（錄音以網址提供）。 */
    public static function answersOf(int $userId, int $fishId): array
    {
        return self::where('user_id', $userId)
            ->where('fish_id', $fishId)
            ->orderBy('id')
            ->get()
            ->map(fn (self $row) => [
                'tribe' => $row->tribe,
                'name' => $row->name,
                'food_category' => $row->food_category,
                'recording_url' => $row->recording_filename === null ? null : self::recordingUrl($row->recording_filename),
                'recording_seconds' => $row->recording_seconds,
            ])
            ->all();
    }

    /**
     * 各部落說法彙總（依設定檔的部落順序）。
     * 人數＝說出相同寫法的不同使用者數；寫法以去前後空白、不分大小寫判斷相同。
     * 錄音只取「其他人」的：排除 $viewerId 自己的（自己的錄音在 answersOf，畫面上放在「我的回答」）。
     */
    public static function summaryForFish(int $fishId, ?int $viewerId = null): array
    {
        $byTribe = self::where('fish_id', $fishId)->orderBy('name_submitted_at')->orderBy('id')->get()->groupBy('tribe');
        $categories = self::selectableFoodCategories();

        return array_map(function (string $tribe) use ($byTribe, $categories, $viewerId) {
            $rows = $byTribe->get($tribe, collect());
            $names = self::groupNames($rows, $viewerId);

            return [
                'tribe' => $tribe,
                'has_responses' => $rows->isNotEmpty(),
                'names' => $names,
                'recordings_only' => self::recordingsOnly($rows, $viewerId),
                'has_variants' => count($names) > 1,
                'categories' => array_map(fn (string $category) => [
                    'value' => $category,
                    'count' => $rows->where('food_category', $category)->count(),
                ], $categories),
            ];
        }, config('fish_options.tribes'));
    }

    /** 回答過這條魚的不同使用者人數（不分部落，同一人答多個部落只算 1 人）。 */
    public static function responderCount(int $fishId): int
    {
        return self::where('fish_id', $fishId)->distinct()->count('user_id');
    }

    /**
     * 魚島部落頁的魚卡彙總：一批魚在某個部落的回答人數（只答叫法、分類或錄音任一項都算）、叫法多數、
     * 最早回答的前 ISLAND_CARD_RESPONDERS 位回答者（魚島角色名；沒有魚島角色為 null）。
     * 同一人同魚同部落只有一筆（唯一鍵），所以列數＝人數。沒有人回答的魚不在結果裡。
     *
     * @param  list<int>  $fishIds
     * @return array<int, array{responder_count: int, majority_name: string|null, first_responders: list<array{name: string|null}>}>
     */
    public static function islandCardsInTribe(array $fishIds, string $tribe): array
    {
        return self::whereIn('fish_tribe_responses.fish_id', $fishIds)
            ->where('fish_tribe_responses.tribe', $tribe)
            ->leftJoin('island_characters', 'island_characters.user_id', '=', 'fish_tribe_responses.user_id')
            ->select('fish_tribe_responses.*', 'island_characters.name as character_name')
            ->get()
            ->groupBy('fish_id')
            ->map(fn ($rows) => [
                'responder_count' => $rows->count(),
                'majority_name' => self::majorityName($rows->sortBy(fn (self $row) => [$row->name_submitted_at, $row->id])->values()),
                'first_responders' => $rows->sortBy(fn (self $row) => [$row->created_at, $row->id])
                    ->take(self::ISLAND_CARD_RESPONDERS)
                    ->map(fn (self $row) => ['name' => $row->character_name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /** 某個部落有人回答過的魚條數（只算未軟刪除的魚）。 */
    public static function answeredFishCountInTribe(string $tribe): int
    {
        return self::where('tribe', $tribe)->whereHas('fish')->distinct()->count('fish_id');
    }

    /**
     * 兩個叫法是否算同一種寫法：去前後空白，且只有英文字母（ASCII）不分大小寫。
     * strtolower 只處理 ASCII，不會把希臘文、帶音標字母等折成同一個。
     */
    public static function sameSpelling(string $a, string $b): bool
    {
        return self::spellingKey($a) === self::spellingKey($b);
    }

    private static function spellingKey(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * @return list<array{name: string, count: int, recordings: list<array{recording_url: string, recording_seconds: int|null}>}>
     *                                                                                                                            人數多者在前，同數依該寫法最早被送出的時間；
     *                                                                                                                            recordings＝該組「其他人」（排除 $viewerId）的全部錄音，依錄音送出時間由早到晚
     */
    private static function groupNames($rows, ?int $viewerId): array
    {
        return array_map(fn (array $group) => [
            'name' => $group['name'],
            'count' => $group['count'],
            'recordings' => self::recordingClips(collect($group['rows'])->filter(
                fn (self $row) => $row->recording_filename !== null && $row->user_id !== $viewerId
            )),
        ], self::spellingGroups($rows));
    }

    /**
     * 依寫法分組（沒填叫法的不算）：每組的寫法取最先出現的那一筆，人多者在前、同數保留先出現的順序。
     * $rows 需已依叫法送出時間排序。
     *
     * @return list<array{name: string, count: int, rows: list<self>}>
     */
    private static function spellingGroups($rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $key = self::spellingKey($name);
            $groups[$key] ??= ['name' => $name, 'count' => 0, 'rows' => []];
            $groups[$key]['count']++;
            $groups[$key]['rows'][] = $row;
        }

        // PHP 的 usort 是穩定排序，同人數時保留先出現（最早送出）的順序
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $groups;
    }

    /**
     * 叫法的多數：填叫法的人數達 MAJORITY_MIN_RESPONDERS，且第一名嚴格多於第二名；否則 null。
     * $rows 是同一條魚、同一個部落的回答，需已依叫法送出時間排序。魚島部落頁與作答頁共用。
     */
    public static function majorityName($rows): ?string
    {
        $groups = self::spellingGroups($rows);
        $answered = array_sum(array_column($groups, 'count'));
        if ($answered < self::MAJORITY_MIN_RESPONDERS) {
            return null;
        }
        $first = $groups[0]['count'];
        $second = $groups[1]['count'] ?? 0;

        return $first > $second ? $groups[0]['name'] : null;
    }

    /** @return list<array{recording_url: string, recording_seconds: int|null}> 依錄音送出時間（相同時依編號）由早到晚 */
    private static function recordingClips($rows): array
    {
        return $rows
            ->sort(fn (self $a, self $b) => self::recordedBefore($a, $b) ? -1 : 1)
            ->map(fn (self $row) => ['recording_url' => self::recordingUrl($row->recording_filename), 'recording_seconds' => $row->recording_seconds])
            ->values()
            ->all();
    }

    /** @return list<array{recording_url: string, recording_seconds: int|null}> 其他人沒有叫法、只有錄音的回答，依錄音送出時間（相同時依編號）排列 */
    private static function recordingsOnly($rows, ?int $viewerId): array
    {
        return self::recordingClips(
            $rows->filter(fn (self $row) => trim((string) $row->name) === '' && $row->recording_filename !== null && $row->user_id !== $viewerId)
        );
    }

    /** 錄音是否比另一筆早送出：先比微秒精度的時間，相同再比編號。 */
    private static function recordedBefore(self $row, ?self $other): bool
    {
        return $other === null
            || [$row->recording_submitted_at, $row->id] < [$other->recording_submitted_at, $other->id];
    }
}
