<?php

namespace App\Http\Controllers;

use App\Services\LineLoginService;
use App\Services\LineUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LineLoginController extends Controller
{
    public function __construct(
        private LineLoginService $lineLoginService,
        private LineUserService $lineUserService,
    ) {}

    /**
     * 將使用者導向 LINE OAuth 授權頁面
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('line_login_state', $state);

        return redirect($this->lineLoginService->getRedirectUrl($state));
    }

    /**
     * 處理 LINE OAuth 回調，建立或取得使用者並登入
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');

        if ($state !== $request->session()->pull('line_login_state')) {
            return redirect('/login');
        }

        $profile = $this->lineLoginService->getUserProfile($request->query('code'));

        $user = $this->lineUserService->upsert(
            $profile['userId'],
            $profile['displayName'],
            $profile['pictureUrl'] ?? null,
        );

        Auth::login($user, remember: true);

        return redirect('/fishs');
    }
}
