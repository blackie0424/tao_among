<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * 每次透過 Eloquent 更新（含 save、軟刪除、還原、整批 update）都把 revision 加 1，
 * 讓「這筆在某個時間點之後有沒有被改過」可以可靠辨認（時間戳只有秒精度、改回原值也看不出來）。
 * 資料修正指令（fish-audios:repair）靠 revision 判斷備份後是否被人編輯。
 */
class FishAudioBuilder extends Builder
{
    public function update(array $values)
    {
        $column = $this->getQuery()->getGrammar()->wrap('revision');

        return parent::update($values + ['revision' => new Expression("{$column} + 1")]);
    }
}
