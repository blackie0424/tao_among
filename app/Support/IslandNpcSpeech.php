<?php

namespace App\Support;

/**
 * 魚島家屋 NPC 說的話（#189 AC 7）：整句＝「這是 {魚名}。我們 {部落} {分類句}；{處理句}。」＋問句（＋結束語）。
 * 「多數」＝這個部落這一題筆數最多的選項，平手時第一名全部列出（依設定順序），不設人數門檻。
 */
final class IslandNpcSpeech
{
    public const NO_FISH = '這裡還沒有魚';

    private const UNNAMED = '這條還沒有名字的魚';

    private const FINISHED = '這裡的魚都講完了。';

    private const NOT_EATEN = '不食用';

    private const QUESTIONS = [
        'name' => '它還沒有名字，你知道我們怎麼叫它嗎？',
        'food_category' => '你知道它算哪一類嗎？',
        'processing_method' => '你知道怎麼處理它嗎？',
    ];

    /**
     * @param  list<string>  $categories  分類的多數（平手時多個，依設定順序）；沒結果為空
     * @param  list<string>  $methods  處理方式的多數（同上，不含「我不知道」）
     * @param  string|null  $ask  要問的那一題（name／food_category／processing_method），沒有為 null
     */
    public static function say(?string $fishName, string $tribe, array $categories, array $methods, ?string $ask, bool $isLast): string
    {
        $name = $fishName ?? self::UNNAMED;
        $speech = "這是 {$name}。我們 {$tribe} ".self::categorySentence($categories).'；'.self::methodSentence($methods).'。';

        return $speech.($ask === null ? '' : self::QUESTIONS[$ask]).($isLast ? self::FINISHED : '');
    }

    /**
     * 這個部落一題的多數：筆數最多的選項（平手全部列出），保留傳入的順序。
     *
     * @param  list<array{value: string, count: int}>  $choices
     * @return list<string>
     */
    public static function leaders(array $choices): array
    {
        if ($choices === []) {
            return [];
        }
        $top = max(array_column($choices, 'count'));

        return array_values(array_column(array_filter($choices, fn (array $choice) => $choice['count'] === $top), 'value'));
    }

    private static function categorySentence(array $categories): string
    {
        return match (count($categories)) {
            0 => '還沒有分類的答案',
            1 => "多數人說它是 {$categories[0]}",
            2 => "有人說它是 {$categories[0]}，也有一樣多人說是 {$categories[1]}",
            default => '有一樣多人分別說是 '.implode('、', $categories),
        };
    }

    private static function methodSentence(array $methods): string
    {
        $eaten = array_map(fn (string $method) => $method === self::NOT_EATEN ? '不吃' : "{$method}吃", $methods);

        return match (count($methods)) {
            0 => '怎麼處理還沒有答案',
            1 => $methods[0] === self::NOT_EATEN ? '多數人說不吃它' : "多數人說抓回來{$eaten[0]}",
            2 => "怎麼處理，有人說{$eaten[0]}，也有一樣多人說{$eaten[1]}",
            default => '怎麼處理，有一樣多人分別說'.implode('、', $eaten),
        };
    }
}
