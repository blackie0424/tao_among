<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * 魚島角色：玩家在魚島上給別人看的名字與自己的部落，一個帳號一個。
 * 與帳號名稱（users.name）分開；among 的頁面不使用。
 */
class IslandCharacter extends Model
{
    public const NAME_MAX_LENGTH = 20;

    /** 沒登入的人設角色時建立的訪客帳號，users.source 的值；分辨訪客一律看這個 */
    public const GUEST_SOURCE = 'island_guest';

    private const GUEST_ROLE = 'guest';

    protected $fillable = ['user_id', 'name', 'tribe'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** #232 圖鑑：在海邊收集過的魚 */
    public function collectedFish(): HasMany
    {
        return $this->hasMany(IslandCollectedFish::class);
    }

    /**
     * #232 把海邊拿到的魚收進圖鑑：自己部落要有這條魚的記錄才收（回傳 false）；
     * 已經收過的不變（收集時間維持第一次）。
     */
    public function collect(Fish $fish): bool
    {
        // 檢查時鎖住那一筆記錄：同時在清空或刪除它的寫入會先完成，這裡才判斷，檢查與新增之間不會被改掉
        return DB::transaction(function () use ($fish) {
            if (! FishTribeResponse::hasRecord($this->user_id, $fish->id, $this->tribe, lock: true)) {
                return false;
            }
            IslandCollectedFish::insertOrIgnore([[
                'island_character_id' => $this->id,
                'fish_id' => $fish->id,
                'collected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            return true;
        });
    }

    /**
     * #232 圖鑑：依收集時間（同時間依收集紀錄編號）排，軟刪除的魚不列、編號依序從 1 補齊；
     * my＝自己部落這條魚的記錄（沒記的題目為 null）。
     *
     * @return list<array{fish_id: int, number: int, my: array{name: ?string, food_category: ?string, processing_method: ?string}}>
     */
    public function collection(): array
    {
        $fishIds = $this->collectedFish()
            ->whereHas('fish')
            ->orderBy('collected_at')
            ->orderBy('id')
            ->pluck('fish_id');
        $records = FishTribeResponse::where(['user_id' => $this->user_id, 'tribe' => $this->tribe])
            ->whereIn('fish_id', $fishIds)
            ->get()
            ->keyBy('fish_id');

        return $fishIds->values()->map(fn (int $fishId, int $index) => [
            'fish_id' => $fishId,
            'number' => $index + 1,
            'my' => array_map(
                fn (string $question) => $records->get($fishId)?->{$question},
                array_combine(FishTribeResponse::ISLAND_QUESTIONS, FishTribeResponse::ISLAND_QUESTIONS)
            ),
        ])->all();
    }

    public static function of(?User $user): ?self
    {
        return $user === null ? null : self::where('user_id', $user->id)->first();
    }

    /** 替既有帳號建立角色；已有角色時回傳原本的，不修改。 */
    public static function createFor(User $user, string $name, string $tribe): self
    {
        self::insertOrIgnore([[
            'user_id' => $user->id,
            'name' => $name,
            'tribe' => $tribe,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        return self::where('user_id', $user->id)->firstOrFail();
    }

    /**
     * 建立魚島訪客帳號（沒有 email 與密碼）與它的角色，兩者同一個交易。
     * role 不在 User 的 fillable，另外指定。
     */
    public static function createGuest(string $name, string $tribe): User
    {
        return DB::transaction(function () use ($name, $tribe) {
            $user = new User(['name' => $name, 'source' => self::GUEST_SOURCE]);
            $user->role = self::GUEST_ROLE;
            $user->save();
            self::createFor($user, $name, $tribe);

            return $user;
        });
    }
}
