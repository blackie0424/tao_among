<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    /** LINE 登入入口：把安全的 redirect 存進 session，完成登入時取用 */
    public static function remember(Request $request): void
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && self::isSafe($redirect)) {
            $request->session()->put(self::INTENDED_KEY, $redirect);
        }
    }

    /** 登入完成後的導向：redirect 參數，其次 session 的 intended，最後首頁；未開通的帳號一律回首頁 */
    public static function after(Request $request, User $user): RedirectResponse
    {
        $intended = $request->session()->pull(self::INTENDED_KEY);
        $target = self::resolveTarget($request->input('redirect'), $intended);

        if (! $user->canBrowse()) {
            return redirect(self::HOME)->with('info', self::PENDING_APPROVAL_MESSAGE);
        }

        return redirect($target);
    }

    private static function resolveTarget(mixed $explicit, mixed $intended): string
    {
        if (is_string($explicit) && self::isSafe($explicit)) {
            return $explicit;
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
