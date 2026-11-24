<?php

namespace App\Http\Middleware;

use App\Models\IslandCharacter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 魚島裡除了角色設定以外的頁面都要先有魚島角色；沒有（含沒登入）就導到角色設定頁。
 * 查到的角色放在 request 的 attributes，controller 用 ATTRIBUTE 取，不再查一次。
 */
class EnsureIslandCharacter
{
    public const ATTRIBUTE = 'island_character';

    public function handle(Request $request, Closure $next): Response
    {
        $character = IslandCharacter::of($request->user());
        if ($character === null) {
            return redirect()->route('island.character');
        }

        $request->attributes->set(self::ATTRIBUTE, $character);

        return $next($request);
    }
}
