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

        if (Auth::attempt($credentials)) {
            // 只允許 source='web' 的管理員帳號透過此表單登入
            if (Auth::user()->source !== 'web') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => '這組帳號密碼無法登入。',
                ]);
            }

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
