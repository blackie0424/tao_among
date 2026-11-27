<?php

namespace App\Support;

/**
 * 魚島部落家屋的四個地點（#189）：魚依編號除以 4 的餘數固定分到一個地點，
 * 新增或刪除魚不會讓其他魚換地點。地點、NPC 稱呼與順序由 chung 確認。
 *
 * #201：每個部落 3 戶，每戶這四個地點，名稱、稱呼、問好語用族語（chung 確認）。
 * 魚依編號除以 12 的餘數分到戶與地點：1～4 第 1 戶、5～8 第 2 戶、9～11 與 0 第 3 戶，
 * 每戶依下面的順序；地點的種類仍等於編號除以 4 的餘數，所以舊平面頁的地點不變。
 */
final class IslandSpot
{
    /** 分配用的除數＝地點數 */
    public const COUNT = 4;

    /** 每個部落幾戶（#201） */
    public const HOUSEHOLDS = 3;

    /** 分到戶與地點用的除數＝戶數 × 地點數 */
    public const HOUSEHOLD_DIVISOR = self::HOUSEHOLDS * self::COUNT;

    /** 依畫面順序；remainder＝魚編號除以 COUNT 的餘數 */
    private const SPOTS = [
        ['code' => 'main-house', 'name' => 'vahay', 'npc' => 'kaminan', 'greeting' => 'kaminan kong', 'remainder' => 1],
        ['code' => 'backrest-stone', 'name' => 'pananadengan', 'npc' => 'akay', 'greeting' => 'akay kong', 'remainder' => 2],
        ['code' => 'drying-yard', 'name' => 'zazawan', 'npc' => 'maran', 'greeting' => 'maran kong', 'remainder' => 3],
        ['code' => 'pavilion', 'name' => 'tagakal', 'npc' => 'kaka', 'greeting' => 'kaka kong', 'remainder' => 0],
    ];

    /** @return list<array{code: string, name: string, npc: string, greeting: string, remainder: int}> */
    public static function all(): array
    {
        return self::SPOTS;
    }

    /** @return array{code: string, name: string, npc: string, greeting: string, remainder: int}|null 代碼不在清單裡時為 null */
    public static function find(string $code): ?array
    {
        foreach (self::SPOTS as $spot) {
            if ($spot['code'] === $code) {
                return $spot;
            }
        }

        return null;
    }

    /** 給畫面的地點資料（不含分配用的餘數） */
    public static function props(array $spot): array
    {
        return ['code' => $spot['code'], 'name' => $spot['name'], 'npc' => $spot['npc']];
    }

    /**
     * 3 戶 × 4 個地點，依戶、再依地點的順序（#201）：
     * 每筆是地點加上 household（第幾戶，1 起算）與 remainder（編號除以 HOUSEHOLD_DIVISOR 的餘數）。
     *
     * @return list<array{household: int, code: string, name: string, npc: string, greeting: string, remainder: int}>
     */
    public static function householdSpots(): array
    {
        $spots = [];
        foreach (range(1, self::HOUSEHOLDS) as $household) {
            foreach (self::SPOTS as $position => $spot) {
                $remainder = (($household - 1) * self::COUNT + $position + 1) % self::HOUSEHOLD_DIVISOR;
                $spots[] = ['household' => $household, ...$spot, 'remainder' => $remainder];
            }
        }

        return $spots;
    }
}
