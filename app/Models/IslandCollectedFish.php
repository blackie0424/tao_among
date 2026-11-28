<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** #232 魚島圖鑑的一筆：角色在海邊收集過的魚與收集時間。 */
class IslandCollectedFish extends Model
{
    protected $table = 'island_collected_fish';

    protected $fillable = ['island_character_id', 'fish_id', 'collected_at'];

    protected $casts = ['collected_at' => 'datetime'];

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class);
    }
}
