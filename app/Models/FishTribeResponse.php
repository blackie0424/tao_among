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

    /** 魚島作答：一題至少要有這麼多人回答才可能標出多數 */
    public const ISLAND_MAJORITY_MIN_RESPONSES = 3;

    /** 魚島一次送出一題時可以送的題目（欄位名），依作答順序 */
    public const ISLAND_QUESTIONS = ['name', 'food_category', 'processing_method'];

    /** 魚卡結果（#183）的兩題：飲食分類、食用方式（處理方式） */
    public const ISLAND_RESULT_QUESTIONS = ['food_category', 'processing_method'];

    protected $fillable = ['user_id', 'fish_id', 'tribe', 'name', 'food_category', 'processing_method', 'respondent_tribe', 'name_submitted_at', 'recording_filename', 'recording_submitted_at', 'recording_seconds'];

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
            // 魚島答的處理方式也算這一筆還有內容：among 魚頁看不到它，清空叫法與分類時不擋
            if ($row === null || (! $hasNewContent && $row->recording_filename === null && $row->processing_method === null)) {
                throw ValidationException::withMessages(['name' => self::EMPTY_RESPONSE_MESSAGE]);
            }
            $replaced = $recording === null ? null : $row->recording_filename;
            $row->fill(['food_category' => $foodCategory, ...self::nameAttributes($row, $name), ...self::recordingAttributes($recording, $recordingSeconds)])->save();

            return $replaced;
        }, attempts: self::DEADLOCK_RETRY_ATTEMPTS);
    }

    /**
     * 魚島一次送出一題：只改這一題的欄位，其餘題目與錄音維持原值；
     * 每次都把回答者自己的部落更新成送出當下角色的部落。
     *
     * 新增與鎖定的做法同 submit（先 INSERT IGNORE，已存在才鎖住該列更新），
     * 所以同一欄位是後取得鎖的生效，不同欄位（含 among 魚頁同時送出的叫法、分類）彼此保留。
     * 叫法換成同一種寫法時保留原寫法與送出時間（同 submit）。
     *
     * @param  string  $question  ISLAND_QUESTIONS 之一（呼叫端已驗證）
     */
    public static function submitQuestion(array $keys, string $question, string $value, string $respondentTribe): void
    {
        $newRow = [
            ...$keys,
            $question => $value,
            'name_submitted_at' => $question === 'name' ? self::submissionTime() : null,
            'respondent_tribe' => $respondentTribe,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (self::insertOrIgnore([$newRow]) === 1) {
            return;
        }

        DB::transaction(function () use ($keys, $question, $value, $respondentTribe, $newRow) {
            $row = self::where($keys)->lockForUpdate()->first();
            // 先前的新增因為已存在而被略過，但那一筆在這之間被刪除：照鎖的先後，現在重新新增
            if ($row === null) {
                self::insert($newRow);

                return;
            }
            $answer = $question === 'name' ? self::nameAttributes($row, $value) : [$question => $value];
            $row->fill([...$answer, 'respondent_tribe' => $respondentTribe])->save();
        }, attempts: self::DEADLOCK_RETRY_ATTEMPTS);
    }

    /**
     * 刪除作者自己在某部落的錄音。先刪檔案，成功才改資料表（失敗時資料表不變，可重試）；
     * 刪後叫法、分類、處理方式、錄音皆空就連整筆回答一起刪。
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
            if ($row->name === null && $row->food_category === null && $row->processing_method === null) {
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

    /**
     * 魚島作答頁：某部落、某條魚三題各自的選項、我的回答與結果（依 ISLAND_QUESTIONS 的順序）。
     * 叫法選項＝正式魚名（未命名時不列）＋這個部落已有的寫法（同一種寫法只列一次）。
     *
     * @return list<array{key: string, options: list<string>, my_answer: string|null, result: array}>
     */
    public static function islandQuestions(int $fishId, string $tribe, string $fishName, int $viewerId): array
    {
        $rows = self::where(['fish_id' => $fishId, 'tribe' => $tribe])->orderBy('created_at')->orderBy('id')->get();
        $responders = self::islandResponders($rows);
        $mine = $rows->firstWhere('user_id', $viewerId);
        $fixedOptions = [
            'food_category' => self::selectableFoodCategories(),
            'processing_method' => config('fish_options.response_processing_methods'),
        ];

        $formalName = $fishName === self::UNNAMED_FISH_NAME ? [] : [$fishName];

        return array_map(function (string $question) use ($rows, $responders, $mine, $fixedOptions, $formalName, $tribe, $fishName) {
            $result = self::islandResult($rows, $responders, $question, $fixedOptions[$question] ?? $formalName, $tribe);
            $options = $fixedOptions[$question] ?? self::islandNameOptions($fishName, array_column($result['choices'], 'value'));

            return ['key' => $question, 'options' => $options, 'my_answer' => $mine?->{$question}, 'result' => $result];
        }, self::ISLAND_QUESTIONS);
    }

    /** @return list<string> 正式魚名在前，接著這個部落已有的寫法；同一種寫法只列一次 */
    private static function islandNameOptions(string $fishName, array $spellings): array
    {
        $options = $fishName === self::UNNAMED_FISH_NAME ? [] : [$fishName];
        foreach ($spellings as $spelling) {
            if (! collect($options)->contains(fn (string $option) => self::sameSpelling($option, $spelling))) {
                $options[] = $spelling;
            }
        }

        return $options;
    }

    /**
     * 一題的結果：選項人數（含訪客、別部落與沒有魚島角色的人）、其中本部落人數、多數與回答者清單。
     * $options 先列出（0 人也列）：固定選項的題目是全部選項，叫法是正式魚名；叫法另列有人答的寫法，人數多者在前。
     * 叫法「我不知道」不是一種寫法（among 魚頁可能送出），不列、不算人數。
     */
    private static function islandResult($rows, array $responders, string $question, array $options, string $tribe): array
    {
        $answered = $rows->filter(fn (self $row) => trim((string) $row->{$question}) !== ''
            && ($question !== 'name' || ! self::sameSpelling($row->name, self::UNNAMED_FISH_NAME)));
        $keyOf = fn (string $value) => $question === 'name' ? self::spellingKey($value) : $value;
        $groups = [];
        foreach ($options as $option) {
            $groups[$keyOf($option)] = ['value' => $option, 'responders' => []];
        }
        foreach ($answered as $row) {
            $key = $keyOf($row->{$question});
            $groups[$key] ??= ['value' => trim($row->{$question}), 'responders' => []];
            $groups[$key]['responders'][] = $responders[$row->id];
        }
        $choices = array_map(fn (array $group) => [...$group, 'count' => count($group['responders'])], array_values($groups));
        if ($question === 'name') {
            // usort 是穩定排序，同人數時保留先出現（最早回答）的順序
            usort($choices, fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        return [
            'total' => $answered->count(),
            'own_tribe_count' => $answered->where('respondent_tribe', $tribe)->count(),
            'majority' => self::majorityOf($choices, $answered->count()),
            'choices' => $choices,
        ];
    }

    /** 回答人數 ≥ ISLAND_MAJORITY_MIN_RESPONSES 且第一名嚴格多於第二名才有多數。 */
    private static function majorityOf(array $choices, int $total): ?string
    {
        if ($total < self::ISLAND_MAJORITY_MIN_RESPONSES) {
            return null;
        }
        $ranked = collect($choices)->sortByDesc('count')->values();

        return $ranked[0]['count'] > ($ranked[1]['count'] ?? 0) ? $ranked[0]['value'] : null;
    }

    /**
     * 每一筆回答的回答者：名字取目前的魚島角色（沒有角色為 null，不用 users.name），
     * 部落取這一筆送出時記下的回答者部落（沒有為 null）。
     *
     * @return array<int, array{name: string|null, tribe: string|null, is_guest: bool}> 以回答編號為鍵
     */
    private static function islandResponders($rows): array
    {
        $userIds = $rows->pluck('user_id')->unique();
        $characters = IslandCharacter::whereIn('user_id', $userIds)->pluck('name', 'user_id');
        $guests = User::whereIn('id', $userIds)->where('source', IslandCharacter::GUEST_SOURCE)->pluck('id')->flip();

        return $rows->mapWithKeys(fn (self $row) => [$row->id => [
            'name' => $characters[$row->user_id] ?? null,
            'tribe' => $row->respondent_tribe,
            'is_guest' => isset($guests[$row->user_id]),
        ]])->all();
    }

    /** @return array{food_category: list<string>, processing_method: list<string>} 魚卡結果兩題各自計算的選項（依設定順序） */
    public static function islandResultOptions(): array
    {
        return [
            'food_category' => self::selectableFoodCategories(),
            'processing_method' => config('fish_options.response_processing_methods'),
        ];
    }

    /**
     * 「這條魚在這個部落有結果」的條件（套在魚的查詢上）：
     * 該部落分類至少 1 筆，而且食用方式至少 1 筆不是「我不知道」；只算選項清單內的值。
     *
     * @return array{0: string, 1: list<mixed>} SQL 布林運算式與綁定值
     */
    public static function islandHasResultSql(string $tribe, string $fishIdColumn): array
    {
        $category = self::islandQuestionRows($tribe, $fishIdColumn, 'food_category', '1');
        $method = self::islandQuestionRows($tribe, $fishIdColumn, 'processing_method', '1');

        return [
            "(exists ({$category->toSql()}) and exists ({$method->toSql()}))",
            [...$category->getBindings(), ...$method->getBindings()],
        ];
    }

    /**
     * 地點清單與 NPC 的排序依據（#189，套在魚的查詢上），兩個 SQL 運算式各帶自己的綁定值：
     * has_food_category／has_processing_method＝這一題在這個部落有沒有結果（0 或 1）；
     * answered＝這個部落有結果的題數（兩題相加）；
     * response_count＝這個部落兩題各自清單內回答筆數的和（食用不算「我不知道」）。
     *
     * @return array<'has_food_category'|'has_processing_method'|'answered'|'response_count', array{0: string, 1: list<mixed>}>
     */
    public static function islandSpotOrderSql(string $tribe, string $fishIdColumn): array
    {
        $existsCategory = self::islandQuestionRows($tribe, $fishIdColumn, 'food_category', '1');
        $existsMethod = self::islandQuestionRows($tribe, $fishIdColumn, 'processing_method', '1');
        $countCategory = self::islandQuestionRows($tribe, $fishIdColumn, 'food_category', 'count(*)');
        $countMethod = self::islandQuestionRows($tribe, $fishIdColumn, 'processing_method', 'count(*)');
        $hasCategory = "(case when exists ({$existsCategory->toSql()}) then 1 else 0 end)";
        $hasMethod = "(case when exists ({$existsMethod->toSql()}) then 1 else 0 end)";

        return [
            'has_food_category' => [$hasCategory, $existsCategory->getBindings()],
            'has_processing_method' => [$hasMethod, $existsMethod->getBindings()],
            'answered' => [
                "({$hasCategory} + {$hasMethod})",
                [...$existsCategory->getBindings(), ...$existsMethod->getBindings()],
            ],
            'response_count' => [
                "(({$countCategory->toSql()}) + ({$countMethod->toSql()}))",
                [...$countCategory->getBindings(), ...$countMethod->getBindings()],
            ],
        ];
    }

    /**
     * 要問的那一題（#189）：依叫法 → 分類 → 處理方式，第一個缺的。
     * 叫法缺＝魚沒有名字（與魚卡顯示「還沒有名字」同一個判斷）；分類、處理方式缺＝那一題在這個部落沒結果。三題都不缺時為 null。
     */
    public static function islandQuestionToAsk(string $fishName, bool $hasCategory, bool $hasMethod): ?string
    {
        return match (true) {
            $fishName === self::UNNAMED_FISH_NAME => 'name',
            ! $hasCategory => 'food_category',
            ! $hasMethod => 'processing_method',
            default => null,
        };
    }

    /** 這個部落、這條魚某一題「算結果」的回答列：只算選項清單內的值，食用不算「我不知道」。 */
    private static function islandQuestionRows(string $tribe, string $fishIdColumn, string $question, string $select)
    {
        $values = self::islandResultOptions()[$question];
        if ($question === 'processing_method') {
            $values = array_values(array_diff($values, [self::unknownProcessingMethod()]));
        }
        $rows = self::query()->toBase()->selectRaw($select)->whereColumn('fish_tribe_responses.fish_id', $fishIdColumn);

        return self::whereExactlyIn(self::whereExactlyIn($rows, 'tribe', [$tribe]), $question, $values);
    }

    /**
     * 魚卡結果：每條魚、每題、每個部落各選項的回答筆數（一筆＝一人替一個部落答一條魚）。
     * 只算選項清單內的值與部落清單內的部落；選項依設定順序，0 筆的選項與部落不列。
     *
     * @param  list<int>  $fishIds
     * @return array<int, array{food_category: array<string, list<array{value: string, count: int}>>, processing_method: array<string, list<array{value: string, count: int}>>}>
     */
    public static function islandResultCounts(array $fishIds): array
    {
        $tribes = config('fish_options.tribes');
        $results = [];
        foreach (self::islandResultOptions() as $question => $options) {
            $query = self::query()->whereIn('fish_id', $fishIds);
            $rows = self::whereExactlyIn(self::whereExactlyIn($query, 'tribe', $tribes), $question, $options)
                ->groupBy('fish_id', 'tribe', $question)
                ->selectRaw("fish_id, tribe, {$question} as value, count(*) as count")
                ->get();
            foreach ($rows->groupBy('fish_id') as $fishId => $fishRows) {
                $results[$fishId][$question] = self::countsByTribe($fishRows, $tribes, $options);
            }
        }

        return array_map(fn (array $questions) => [
            'food_category' => $questions['food_category'] ?? [],
            'processing_method' => $questions['processing_method'] ?? [],
        ], $results);
    }

    /** 食用方式的「我不知道」：不算有結果、不畫進魚卡。 */
    public static function unknownProcessingMethod(): string
    {
        return config('fish_options.response_unknown_processing_method');
    }

    /**
     * 欄位值與清單「逐位元組相同」才算：MySQL 預設 collation 不分大小寫、尾端空白視為相同，
     * 所以 MySQL 先轉成二進位再比；SQLite 預設就是逐位元組比對。
     *
     * @template T of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  T  $query
     * @return T
     */
    private static function whereExactlyIn($query, string $column, array $values)
    {
        if ($query->getConnection()->getDriverName() !== 'mysql') {
            return $query->whereIn($column, $values);
        }

        $placeholders = implode(', ', array_fill(0, count($values), 'cast(? as binary)'));

        return $query->whereRaw("cast({$query->getGrammar()->wrap($column)} as binary) in ({$placeholders})", $values);
    }

    /** @return array<string, list<array{value: string, count: int}>> 依部落清單順序；部落內依選項順序 */
    private static function countsByTribe($rows, array $tribes, array $options): array
    {
        $byTribe = [];
        foreach ($tribes as $tribe) {
            $counts = $rows->where('tribe', $tribe)->pluck('count', 'value');
            $choices = array_values(array_filter(array_map(
                fn (string $option) => isset($counts[$option]) ? ['value' => $option, 'count' => (int) $counts[$option]] : null,
                $options,
            )));
            if ($choices !== []) {
                $byTribe[$tribe] = $choices;
            }
        }

        return $byTribe;
    }

    /** 回答過這條魚的不同使用者人數（不分部落，同一人答多個部落只算 1 人）。 */
    public static function responderCount(int $fishId): int
    {
        return self::where('fish_id', $fishId)->distinct()->count('user_id');
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
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $key = self::spellingKey($name);
            $groups[$key] ??= ['name' => $name, 'count' => 0, 'rows' => []];
            $groups[$key]['count']++;
            if ($row->recording_filename !== null && $row->user_id !== $viewerId) {
                $groups[$key]['rows'][] = $row;
            }
        }

        // PHP 的 usort 是穩定排序，同人數時保留先出現（最早送出）的順序
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_map(fn (array $group) => [
            'name' => $group['name'],
            'count' => $group['count'],
            'recordings' => self::recordingClips(collect($group['rows'])),
        ], $groups);
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
