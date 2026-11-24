<?php

namespace App\Services;

/**
 * 魚名依「、」拆成多個名稱。
 * 每段去頭尾空白、略過空字串、重複的只留第一個；名稱比對為完全相同的字串（大小寫不同視為不同名稱）。
 */
class FishNameSplitter
{
    public const SEPARATOR = '、';

    /** @return list<string> */
    public function split(?string $fishName): array
    {
        $parts = array_map('trim', explode(self::SEPARATOR, (string) $fishName));

        return array_values(array_unique(array_filter($parts, fn (string $part) => $part !== '')));
    }
}
