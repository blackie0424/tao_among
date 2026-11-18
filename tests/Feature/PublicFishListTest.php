<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const PUBLIC_ITEM_KEYS = [
    'display_image_position',
    'display_image_scale',
    'display_image_url',
    'id',
    'image_url',
    'name',
];

beforeEach(function () {
    Http::fake(['*' => Http::response('', 404)]);
});

function pageProps($response): array
{
    return $response->viewData('page')['props'];
}

function sortedKeys(array $row): array
{
    $keys = array_keys($row);
    sort($keys);

    return $keys;
}

// ── AC 1：訪客直接看到清單 ──────────────────────────────────

it('未登入者 GET / 看到魚類清單', function () {
    Fish::factory()->create(['name' => '測試魚甲']);

    $response = $this->get('/')->assertOk();

    expect($response->viewData('page')['component'])->toBe('Fishs');
    expect(pageProps($response)['items'][0]['name'])->toBe('測試魚甲');
});

// ── AC 2、3：訪客與 guest 資料白名單（完全比對）────────────────

const PUBLIC_SEARCH_OPTION_KEYS = [
    'captureLocations',
    'captureMethods',
    'dietaryClassifications',
    'processingMethods',
    'tribes',
];

/** 兩種公開身分：未登入、role 為 guest 的登入者 */
dataset('public viewers', [
    '未登入' => [false],
    'role 為 guest' => [true],
]);

function actAsPublicViewer(bool $isGuestRole): void
{
    if ($isGuestRole) {
        test()->actingAs(User::factory()->lineGuest()->create());
    }
}

it('清單資料只有白名單欄位與固定鍵', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    Fish::factory()->create(['audio_filename' => 'voice.mp3']);

    $props = pageProps($this->get('/')->assertOk());

    expect(sortedKeys($props['items'][0]))->toBe(PUBLIC_ITEM_KEYS);
    expect(sortedKeys($props['pageInfo']))->toBe(['hasMore', 'nextCursor']);
    expect($props['filters'])->toBe([]);
    expect(sortedKeys($props['searchOptions']))->toBe(PUBLIC_SEARCH_OPTION_KEYS);
    foreach ($props['searchOptions'] as $list) {
        expect($list)->toBe([]);
    }
})->with('public viewers');

it('name 搜尋有效：只回符合的魚，filters 只含 name', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    Fish::factory()->create(['name' => 'Alpha']);
    Fish::factory()->create(['name' => 'Beta']);

    $props = pageProps($this->get('/?name=Alpha')->assertOk());

    expect(array_column($props['items'], 'name'))->toBe(['Alpha']);
    expect($props['filters'])->toBe(['name' => 'Alpha']);
})->with('public viewers');

it('searchStats 在 Inertia JSON 裡是空物件而不是空陣列', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    config(['app.asset_url' => 'https://assets.test']);
    $version = app(HandleInertiaRequests::class)->version(request());

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get('/')
        ->assertOk();

    $stats = json_decode($response->getContent())->props->searchStats;
    expect($stats)->toBeObject()->and((array) $stats)->toBe([]);
})->with('public viewers');

it('只接受 name 與游標參數，其他篩選參數被忽略', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    Fish::factory()->create(['name' => 'Alpha']);
    Fish::factory()->create(['name' => 'Beta']);

    $withForbidden = pageProps($this->get('/?tribe=ivalino&food_category=x&processing_method=y&capture_method=z&without_audio=1&capture_location=somewhere')->assertOk());
    $plain = pageProps($this->get('/')->assertOk());

    expect(array_column($withForbidden['items'], 'id'))->toBe(array_column($plain['items'], 'id'));
    expect($withForbidden['filters'])->toBe([]);
})->with('public viewers');

it('禁用參數即使是非字串（陣列）也只被忽略，不報錯', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    Fish::factory()->count(2)->create();

    $plain = pageProps($this->get('/')->assertOk());
    $probed = pageProps($this->get('/?without_audio[]=1&tribe[]=a&capture_location[]=b&capture_method[]=c&processing_method[]=d&food_category[]=e')->assertOk());

    expect(array_column($probed['items'], 'id'))->toBe(array_column($plain['items'], 'id'));
    expect($probed['filters'])->toBe([]);
})->with('public viewers');

it('合法游標驗證不受影響：last_id=0 仍回 422', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);

    $this->getJson('/?last_id=0')->assertStatus(422)->assertJson(['error' => 'INVALID_CURSOR']);
})->with('public viewers');

it('可用游標分頁：第二頁不重複、最後一頁 hasMore 為 false', function (bool $isGuestRole) {
    actAsPublicViewer($isGuestRole);
    Fish::factory()->count(25)->create();

    $first = pageProps($this->get('/?perPage=10')->assertOk());
    expect($first['items'])->toHaveCount(10);
    expect($first['pageInfo']['hasMore'])->toBeTrue();

    $second = pageProps($this->get('/?perPage=10&last_id='.$first['pageInfo']['nextCursor'])->assertOk());
    expect($second['items'])->toHaveCount(10);
    expect(array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')))->toBe([]);

    $third = pageProps($this->get('/?perPage=10&last_id='.$second['pageInfo']['nextCursor'])->assertOk());
    expect($third['items'])->toHaveCount(5);
    expect($third['pageInfo']['hasMore'])->toBeFalse();
})->with('public viewers');

// ── AC 4：詳細頁仍要登入 ────────────────────────────────────

it('未登入者 GET /fish/{id} 被導向登入頁', function () {
    $fish = Fish::factory()->create();

    $this->get("/fish/{$fish->id}")->assertRedirect('/login');
});

it('role 為 guest 的登入者 GET /fish/{id} 回 403', function () {
    $this->actingAs(User::factory()->lineGuest()->create());
    $fish = Fish::factory()->create();

    $this->get("/fish/{$fish->id}")->assertForbidden();
});

// ── AC 5：viewer / editor 照現有規則 ────────────────────────

it('viewer 沒有音檔，editor 有音檔', function () {
    Fish::factory()->create(['audio_filename' => 'voice.mp3']);

    $this->actingAs(User::factory()->lineViewer()->create());
    $viewerItem = pageProps($this->get('/')->assertOk())['items'][0];
    expect($viewerItem['audio_url'] ?? null)->toBeNull();

    $this->actingAs(User::factory()->lineEditor()->create());
    $editorItem = pageProps($this->get('/')->assertOk())['items'][0];
    expect($editorItem['audio_url'])->not->toBeNull();
});

it('viewer 仍可使用進階篩選', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    Fish::factory()->create();

    $props = pageProps($this->get('/?tribe=ivalino')->assertOk());

    expect($props['filters'])->toHaveKey('tribe', 'ivalino');
});

// ── AC 6：/fishs 導向 / 並保留查詢字串 ──────────────────────

it('GET /fishs 導向 / 並保留查詢字串', function () {
    $this->get('/fishs?name=abc&perPage=10')
        ->assertStatus(301)
        ->assertRedirect('/?name=abc&perPage=10');

    $this->get('/fishs')->assertStatus(301)->assertRedirect('/');
});
