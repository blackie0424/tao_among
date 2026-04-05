<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LineLoginService;
use App\Services\LineUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LineLoginController extends Controller
{
    public function __construct(
        private LineLoginService $lineLoginService,
        private LineUserService $lineUserService,
    ) {
    }

    /**
     * 將使用者導向 LINE OAuth 授權頁面
     * state 存入 Cache（資料庫）而非 session，避免跨域問題
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        Cache::put('line_state:' . $state, true, now()->addMinutes(15));

        return redirect($this->lineLoginService->getRedirectUrl($state));
    }

    /**
     * 處理 LINE OAuth 回調
     * 驗證成功後產生一次性 token，導回本機域名完成登入
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');

        if (!$state || !Cache::pull('line_state:' . $state)) {
            return redirect(config('app.url') . '/login');
        }

        $profile = $this->lineLoginService->getUserProfile($request->query('code'));

        $user = $this->lineUserService->upsert(
            $profile['userId'],
            $profile['displayName'],
            $profile['pictureUrl'] ?? null,
        );

        // 產生一次性登入 token，5 分鐘內有效
        $token = Str::random(64);
        Cache::put('line_login_token:' . $token, $user->id, now()->addMinutes(5));

        return redirect(config('app.url') . '/auth/line/complete?token=' . $token);
    }

    /**
     * 用一次性 token 完成登入（在本機域名上執行）
     */
    public function complete(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        $userId = $token ? Cache::pull('line_login_token:' . $token) : null;

        if (!$userId) {
            return redirect('/login');
        }

        $user = User::findOrFail($userId);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/fishs');
    }
}
