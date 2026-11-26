<?php

namespace App\Support;

/**
 * 魚島部落家屋的四個地點（#189）：魚依編號除以 4 的餘數固定分到一個地點，
 * 新增或刪除魚不會讓其他魚換地點。地點、NPC 稱呼與順序由 chung 確認。
 */
final class IslandSpot
{
    /** 分配用的除數＝地點數 */
    public const COUNT = 4;

    /** 依畫面順序；remainder＝魚編號除以 COUNT 的餘數 */
    private const SPOTS = [
        ['code' => 'main-house', 'name' => '主屋', 'npc' => '阿姨', 'remainder' => 1],
        ['code' => 'backrest-stone', 'name' => '靠背石', 'npc' => '爺爺', 'remainder' => 2],
        ['code' => 'drying-yard', 'name' => '曬魚庭院', 'npc' => '叔叔', 'remainder' => 3],
        ['code' => 'pavilion', 'name' => '涼亭', 'npc' => '年輕人', 'remainder' => 0],
    ];

    /** @return list<array{code: string, name: string, npc: string, remainder: int}> */
    public static function all(): array
    {
        return self::SPOTS;
    }

    /** @return array{code: string, name: string, npc: string, remainder: int}|null 代碼不在清單裡時為 null */
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
}
