<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 使用者對某條魚「這是哪個部落的說法」的回答。
 * 與正式資料（魚名、部落分類、發音）完全分開，不回寫。
 */
class FishTribeResponse extends Model
{
    public const NAME_MAX_LENGTH = 60;

    /** 飲食分類選項中不開放給回答者選的值（佔位用） */
    private const UNSELECTABLE_FOOD_CATEGORIES = ['?', '尚未紀錄'];

    /** 魚名為此值代表尚未命名，不列為叫法選項 */
    private const UNNAMED_FISH_NAME = '我不知道';

    protected $fillable = ['user_id', 'fish_id', 'tribe', 'name', 'food_category'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class);
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
        $byTribe = self::where('fish_id', $fishId)->orderBy('id')->get()->groupBy('tribe');
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

    /**
     * 全站進度：至少有一位使用者回答過的魚數／魚總數（只計未刪除的魚）。
     *
     * @return array{answered: int, total: int}
     */
    public static function progress(): array
    {
        return [
            'answered' => Fish::whereIn('id', self::select('fish_id'))->count(),
            'total' => Fish::count(),
        ];
    }

    /** @return list<array{name: string, count: int}> 人數多者在前，同數依最早送出 */
    private static function groupNames($rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $groups[$key] ??= ['name' => $name, 'count' => 0];
            $groups[$key]['count']++;
        }

        // PHP 的 usort 是穩定排序，同人數時保留先出現（最早送出）的順序
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $groups;
    }
}
