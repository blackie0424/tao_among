<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 登入後的去處：只接受站內路徑，並處理尚未開通（guest）的帳號。
 */
class LoginRedirect
{
    private const HOME = '/';

    private const INTENDED_KEY = 'url.intended';

    private const PENDING_APPROVAL_MESSAGE = '你的帳號還在等待開通';

    /** 站內相對路徑才安全：不能是站外網址、協議相對網址、含反斜線或控制字元 */
    public static function isSafe(?string $url): bool
    {
        if ($url === null || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return false;
        }

        return ! preg_match('/[\\\\\x00-\x1F\x7F]/', $url);
    }

    /** LINE 登入入口：有帶 redirect 時，合法的存進 session、不合法的清掉舊目標；沒帶就保留舊目標 */
    public static function remember(Request $request): void
    {
        $redirect = $request->query('redirect');

        if ($redirect === null) {
            return;
        }

        if (is_string($redirect) && self::isSafe($redirect)) {
            $request->session()->put(self::INTENDED_KEY, $redirect);

            return;
        }

        $request->session()->forget(self::INTENDED_KEY);
    }

    /** 登入完成後的導向：有帶 redirect 就只看它（不合法回首頁），沒帶才用 session 的 intended；未開通的帳號一律回首頁 */
    public static function after(Request $request, User $user): RedirectResponse
    {
        $intended = $request->session()->pull(self::INTENDED_KEY);

        // 身分換了，舊身分的歷史頁面（加密過的）不再能還原
        Inertia::clearHistory();

        if (! $user->canBrowse()) {
            return redirect(self::HOME)->with('info', self::PENDING_APPROVAL_MESSAGE);
        }

        return redirect(self::resolveTarget($request->input('redirect'), $intended));
    }

    private static function resolveTarget(mixed $explicit, mixed $intended): string
    {
        if ($explicit !== null) {
            return is_string($explicit) && self::isSafe($explicit) ? $explicit : self::HOME;
        }

        $intendedPath = self::pathOf($intended);

        return self::isSafe($intendedPath) ? $intendedPath : self::HOME;
    }

    /** session 的 intended 可能是完整網址，只取路徑與查詢字串 */
    private static function pathOf(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
