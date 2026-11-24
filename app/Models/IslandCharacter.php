<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
