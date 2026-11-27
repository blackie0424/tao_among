<?php

namespace App\Support;

/**
 * 魚島部落家屋的四個地點（#189）：魚依編號除以 4 的餘數固定分到一個地點，
 * 新增或刪除魚不會讓其他魚換地點。地點、NPC 稱呼與順序由 chung 確認。
 *
 * #201：每個部落 3 戶，每戶這四個地點，名稱、稱呼、問好語用族語（chung 確認）。
 * 魚分到戶與地點的規則寫在 docs/design.md 3.5，留給「面板：讀取」用。
 */
final class IslandSpot
{
    /** 分配用的除數＝地點數 */
    public const COUNT = 4;

    /** 每個部落幾戶（#201） */
    public const HOUSEHOLDS = 3;

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
     * 3D 魚島給畫面的地點（#201）：3 戶 × 4 個地點，依戶、再依地點的順序；
     * 每筆是 household（第幾戶，1 起算）、代碼、名稱、稱呼、問好語。
     *
     * @return list<array{household: int, code: string, name: string, npc: string, greeting: string}>
     */
    public static function householdProps(): array
    {
        $spots = [];
        foreach (range(1, self::HOUSEHOLDS) as $household) {
            foreach (self::SPOTS as $spot) {
                $spots[] = ['household' => $household, ...self::props($spot), 'greeting' => $spot['greeting']];
            }
        }

        return $spots;
    }

    /**
     * 3D 魚島（#201、#216）：這條魚在 householdProps() 裡的第幾筆（0 起算，第 hex 戶 × 4 ＋ 第 slot 件）。
     * 編號除以 12 的餘數 1～4 是第 1 戶、5～8 第 2 戶、9～11 與 0 第 3 戶，每戶依序 vahay、pananadengan、zazawan、tagakal。
     */
    public static function householdIndexOf(int $fishId): int
    {
        $spotCount = self::HOUSEHOLDS * self::COUNT;

        return ($fishId + $spotCount - 1) % $spotCount;
    }
}
