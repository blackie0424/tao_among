<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineLoginService
{
    /**
     * 建立 LINE OAuth 2.0 授權網址
     */
    public function getRedirectUrl(string $state): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('line.login_channel_id'),
            'redirect_uri'  => config('line.login_callback_url'),
            'state'         => $state,
            'scope'         => 'profile openid',
        ]);

        return 'https://access.line.me/oauth2/v2.1/authorize?' . $params;
    }

    /**
     * 用授權碼取得使用者 Profile（包含 token 交換）
     *
     * @return array{userId: string, displayName: string, pictureUrl: ?string}
     */
    public function getUserProfile(string $code): array
    {
        $tokenResponse = Http::asForm()->post('https://api.line.me/oauth2/v2.1/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => config('line.login_callback_url'),
            'client_id'     => config('line.login_channel_id'),
            'client_secret' => config('line.login_channel_secret'),
        ]);

        $accessToken = $tokenResponse->json('access_token');

        $profileResponse = Http::withToken($accessToken)
            ->get('https://api.line.me/v2/profile');

        $profile = $profileResponse->json();

        return [
            'userId'      => $profile['userId'],
            'displayName' => $profile['displayName'],
            'pictureUrl'  => $profile['pictureUrl'] ?? null,
        ];
    }
}
