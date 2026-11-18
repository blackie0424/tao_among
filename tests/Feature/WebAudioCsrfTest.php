<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 網頁錄音頁（同站 SPA 請求）取得音訊上傳網址：重現 #137 的失敗現象與修正後的請求形狀。
// 測試環境預設略過 CSRF 驗證，這裡還原成真實行為。
function postSignedAudioUrlAsBrowser($test, array $headers, ?string $sessionToken = null)
{
    app()['env'] = 'local';
    $fish = Fish::factory()->create();
    $test->actingAs(User::factory()->admin()->create(), 'web');
    if ($sessionToken) {
        $test->withSession(['_token' => $sessionToken]);
    }

    return $test->call('POST', "/prefix/api/fish/{$fish->id}/storage/signed-upload-audio-url", [], [], [], array_merge([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_REFERER' => "http://localhost/fish/{$fish->id}/audio/create",
    ], $headers), json_encode(['filename' => 'a.m4a']));
}

it('網頁請求沒帶 CSRF token 會得到 419 HTML 頁（修正前瀏覽器行為）', function () {
    $res = postSignedAudioUrlAsBrowser($this, []);

    expect($res->getStatusCode())->toBe(419);
    expect($res->headers->get('content-type'))->toContain('text/html');
    expect($res->getContent())->not->toContain('"url"');
});

it('網頁請求帶 CSRF token 與 Accept 會取得上傳網址（修正後）', function () {
    $res = postSignedAudioUrlAsBrowser($this, ['HTTP_X_CSRF_TOKEN' => 'tok123', 'HTTP_ACCEPT' => 'application/json'], 'tok123');

    $res->assertOk()->assertJsonStructure(['url']);
});
