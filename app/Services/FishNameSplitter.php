<?php

namespace App\Services;

/**
 * 魚名依「、」拆成多個名稱。
 * 每段去頭尾空白、略過空字串、重複的只留第一個；名稱比對為完全相同的字串（大小寫不同視為不同名稱）。
 * 「空白」與前端 resources/js/utils/fishSpellings.js 的 String.prototype.trim() 是同一組字元
 * （含全形空白、不換行空白、BOM），兩邊拆出來的拼字才會一致；兩邊的測試用同一組案例對照。
 */
class FishNameSplitter
{
    public const SEPARATOR = '、';

    /** JavaScript trim() 會去掉的字元，逐一列出（不用 \s：PHP 的 \s 會多去掉 U+0085，兩邊就不一致）：Tab、換行、垂直 Tab、換頁、Enter、空白、不換行空白、各種寬度空白、行／段分隔符、全形空白、BOM */
    private const EDGE_WHITESPACE = '[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+';

    /** @return list<string> */
    public function split(?string $fishName): array
    {
        $parts = array_map(fn (string $part) => $this->trimEdges($part), explode(self::SEPARATOR, (string) $fishName));

        return array_values(array_unique(array_filter($parts, fn (string $part) => $part !== '')));
    }

    private function trimEdges(string $part): string
    {
        return preg_replace('/^'.self::EDGE_WHITESPACE.'|'.self::EDGE_WHITESPACE.'$/u', '', $part) ?? $part;
    }
}
