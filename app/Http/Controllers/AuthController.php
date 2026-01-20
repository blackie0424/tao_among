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
        if ($credentials['email'] === 'admin') {
            $credentials['email'] = 'admin@example.com';
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/fishs');
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
}
