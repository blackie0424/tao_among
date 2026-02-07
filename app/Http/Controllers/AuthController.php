<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * 顯示登入頁面
     */
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * 處理登入請求
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        // 快捷處理：若輸入 'admin'，自動轉為 admin@example.com
        // 快捷處理：自動轉換短名為完整 Email
        $shortcuts = [
            'admin' => 'admin@example.com',
            'user1' => 'user1@pongsonotao.org',
            'user2' => 'user2@pongsonotao.org',
        ];

        if (array_key_exists($credentials['email'], $shortcuts)) {
            $credentials['email'] = $shortcuts[$credentials['email']];
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // 從 URL 參數取得 redirect，預設為 /fishs
            $redirect = $request->input('redirect', '/fishs');
            
            // 安全驗證：只允許本站相對路徑，防止 Open Redirect 攻擊
            if (!$this->isValidRedirect($redirect)) {
                $redirect = '/fishs';
            }

            return redirect($redirect);
        }

        return back()->withErrors([
            'email' => '這組帳號密碼無法登入。',
        ]);
    }

    /**
     * 登出
     */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * 驗證 redirect URL 是否安全
     * 只允許本站相對路徑，防止 Open Redirect 攻擊
     */
    private function isValidRedirect(string $url): bool
    {
        // 必須以 / 開頭（相對路徑）
        if (!str_starts_with($url, '/')) {
            return false;
        }
        
        // 不能是協議相對 URL（如 //evil.com）
        if (str_starts_with($url, '//')) {
            return false;
        }
        
        return true;
    }
}
