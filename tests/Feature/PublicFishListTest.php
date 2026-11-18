<?php

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

// ── AC 2：訪客資料白名單（完全比對）─────────────────────────

it('未登入者的清單資料只有白名單欄位', function () {
    Fish::factory()->create(['audio_filename' => 'voice.mp3']);

    $props = pageProps($this->get('/')->assertOk());

    expect(sortedKeys($props['items'][0]))->toBe(PUBLIC_ITEM_KEYS);
    expect(sortedKeys($props['pageInfo']))->toBe(['hasMore', 'nextCursor']);
    expect($props['filters'])->toBe([]);
    expect($props['searchStats'])->toBe([]);
    foreach ($props['searchOptions'] as $list) {
        expect($list)->toBe([]);
    }
});

it('role 為 guest 的登入者拿到與訪客相同的白名單資料', function () {
    $this->actingAs(User::factory()->lineGuest()->create());
    Fish::factory()->create(['audio_filename' => 'voice.mp3']);

    $props = pageProps($this->get('/')->assertOk());

    expect(sortedKeys($props['items'][0]))->toBe(PUBLIC_ITEM_KEYS);
    expect($props['filters'])->toBe([]);
    expect($props['searchStats'])->toBe([]);
});

// ── AC 3：訪客只接受 name / last_id / perPage ───────────────

it('訪客搜尋 name 有效，其他篩選參數被忽略', function () {
    Fish::factory()->create(['name' => 'Alpha']);
    Fish::factory()->create(['name' => 'Beta']);

    $byName = pageProps($this->get('/?name=Alpha')->assertOk());
    expect(array_column($byName['items'], 'name'))->toBe(['Alpha']);
    expect($byName['filters'])->toBe(['name' => 'Alpha']);

    $withForbidden = pageProps($this->get('/?tribe=ivalino&food_category=x&processing_method=y&capture_method=z&without_audio=1&capture_location=somewhere')->assertOk());
    $plain = pageProps($this->get('/')->assertOk());

    expect(array_column($withForbidden['items'], 'id'))->toBe(array_column($plain['items'], 'id'));
    expect($withForbidden['filters'])->toBe([]);
});

it('訪客可用游標分頁：第二頁不重複、最後一頁 hasMore 為 false', function () {
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
});

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
