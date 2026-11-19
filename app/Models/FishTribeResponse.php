<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * 使用者對某條魚「這是哪個部落的說法」的回答。
 * 與正式資料（魚名、部落分類、發音）完全分開，不回寫。
 */
class FishTribeResponse extends Model
{
    public const NAME_MAX_LENGTH = 60;

    private const DEADLOCK_RETRY_ATTEMPTS = 3;

    /** 飲食分類選項中不開放給回答者選的值（佔位用） */
    private const UNSELECTABLE_FOOD_CATEGORIES = ['?', '尚未紀錄'];

    /** 魚名為此值代表尚未命名，不列為叫法選項 */
    private const UNNAMED_FISH_NAME = '我不知道';

    protected $fillable = ['user_id', 'fish_id', 'tribe', 'name', 'food_category', 'name_submitted_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class);
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
     */
    public static function submit(array $keys, ?string $name, ?string $foodCategory): void
    {
        $inserted = self::insertOrIgnore([[
            ...$keys,
            'name' => $name,
            'food_category' => $foodCategory,
            'name_submitted_at' => $name === null ? null : self::submissionTime(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
        if ($inserted === 1) {
            return;
        }

        // 偶發的死鎖由 Laravel 重試
        DB::transaction(function () use ($keys, $name, $foodCategory) {
            $row = self::where($keys)->lockForUpdate()->firstOrFail();
            $row->fill(['food_category' => $foodCategory, ...self::nameAttributes($row, $name)])->save();
        }, attempts: self::DEADLOCK_RETRY_ATTEMPTS);
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
        return array_values(array_diff(config('fish_options.food_categories'), self::UNSELECTABLE_FOOD_CATEGORIES));
    }

    /** @return array{tribes: list<string>, food_categories: list<string>, name_options: list<string>, name_max_length: int} */
    public static function formOptions(string $fishName): array
    {
        return [
            'tribes' => config('fish_options.tribes'),
            'food_categories' => self::selectableFoodCategories(),
            'name_options' => $fishName === self::UNNAMED_FISH_NAME ? [] : [$fishName],
            'name_max_length' => self::NAME_MAX_LENGTH,
        ];
    }

    /** 目前使用者對這條魚已送出的回答。 */
    public static function answersOf(int $userId, int $fishId): array
    {
        return self::where('user_id', $userId)
            ->where('fish_id', $fishId)
            ->orderBy('id')
            ->get(['tribe', 'name', 'food_category'])
            ->map->only(['tribe', 'name', 'food_category'])
            ->all();
    }

    /**
     * 各部落說法彙總（依設定檔的部落順序）。
     * 人數＝說出相同寫法的不同使用者數；寫法以去前後空白、不分大小寫判斷相同。
     */
    public static function summaryForFish(int $fishId): array
    {
        $byTribe = self::where('fish_id', $fishId)->orderBy('name_submitted_at')->orderBy('id')->get()->groupBy('tribe');
        $categories = self::selectableFoodCategories();

        return array_map(function (string $tribe) use ($byTribe, $categories) {
            $rows = $byTribe->get($tribe, collect());
            $names = self::groupNames($rows);

            return [
                'tribe' => $tribe,
                'has_responses' => $rows->isNotEmpty(),
                'names' => $names,
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

    /** @return list<array{name: string, count: int}> 人數多者在前，同數依該寫法最早被送出的時間 */
    private static function groupNames($rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $key = self::spellingKey($name);
            $groups[$key] ??= ['name' => $name, 'count' => 0];
            $groups[$key]['count']++;
        }

        // PHP 的 usort 是穩定排序，同人數時保留先出現（最早送出）的順序
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $groups;
    }
}
