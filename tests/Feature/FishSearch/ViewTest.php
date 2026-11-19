<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use App\Services\FishSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function viewProps($response): array
{
    return $response->viewData('page')['props'];
}

function viewFish(string $name, array $methods = []): Fish
{
    $fish = Fish::factory()->create(['name' => $name]);
    foreach ($methods as $method) {
        CaptureRecord::factory()->create(['fish_id' => $fish->id, 'capture_method' => $method]);
    }

    return $fish;
}

function viewNames(array $props): array
{
    return array_column($props['items'], 'name');
}

function viewOptionCounts(array $props): array
{
    return collect($props['viewOptions'])->pluck('count', 'key')->all();
}

// ── 驗收 1：選項與數字 ────────────────────────────────────────

it('已開通帳號取得依序排列的選項與固定數字，0 筆的看法不列出', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('night-only', ['mamacik']);
    viewFish('both', ['mamacik', 'mamasil']);
    viewFish('day-only', ['mamasil']);
    viewFish('net-only', ['網魚']);
    viewFish('oyod');
    viewFish('OYOD ');
    viewFish('rahet');
    viewFish('Rahet');
    viewFish('rahet');

    $props = viewProps($this->get('/'));

    expect(array_column($props['viewOptions'], 'key'))->toBe(['night', 'day', 'same_name', 'alpha', 'all']);
    expect(viewOptionCounts($props))->toBe([
        'night' => 2,
        'day' => 2,
        'same_name' => 2,
        'alpha' => 9,
        'all' => 9,
    ]);
    expect($props['viewOptions'][0]['label'])->toBe('晚上釣魚會遇到的魚');
    expect($props['viewOptions'][1]['label'])->toBe('白天釣魚會遇到的魚');
    expect($props['view'])->toBe('all');
});

it('沒有夜釣、白天釣紀錄與重名時，三個看法不列出，字母順序與全部仍列出', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('only-one', ['網魚']);

    $props = viewProps($this->get('/'));

    expect(array_column($props['viewOptions'], 'key'))->toBe(['alpha', 'all']);
});

it('editor 與 admin 取得同樣的選項', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create());
    viewFish('night-only', ['mamacik']);

    expect(viewOptionCounts(viewProps($this->get('/')))['night'])->toBe(1);
})->with(['lineEditor', 'admin']);

it('訪客與 guest 沒有選項，view 參數被忽略', function (?string $state) {
    if ($state) {
        $this->actingAs(User::factory()->{$state}()->create());
    }
    viewFish('night-only', ['mamacik']);
    viewFish('plain');

    $plain = viewProps($this->get('/'));
    $withView = viewProps($this->get('/?view=night'));

    $withCursor = viewProps($this->get('/?view=alpha&last_name=a'));

    expect($withView['viewOptions'] ?? null)->toBeNull();
    expect(viewNames($withCursor))->toBe(viewNames($plain));
    expect(viewNames($withView))->toBe(viewNames($plain));
    expect(count($withView['items']))->toBe(2);
})->with([null, 'lineGuest']);

// ── 驗收 2：晚上／白天 ────────────────────────────────────────

it('晚上只回記錄為 mamacik 的魚，白天只回 mamasil，兩種都有的兩邊都出現', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('night-only', ['mamacik']);
    viewFish('both', ['mamacik', 'mamasil']);
    viewFish('day-only', ['mamasil']);
    viewFish('net-only', ['網魚']);
    viewFish('no-record');

    $night = viewProps($this->get('/?view=night'));
    $day = viewProps($this->get('/?view=day'));

    expect(viewNames($night))->toEqualCanonicalizing(['night-only', 'both']);
    expect(viewNames($day))->toEqualCanonicalizing(['both', 'day-only']);
    expect($night['view'])->toBe('night');
    expect(count($night['items']))->toBe(viewOptionCounts($night)['night']);
});

dataset('夜與日', [
    '晚上' => ['night', 'mamacik'],
    '白天' => ['day', 'mamasil'],
]);

it('比對是精確比對：去空白、不分大小寫，只是包含關鍵字的文字不算', function (string $view, string $method) {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('exact-trimmed', ['  '.strtoupper($method).' ']);
    viewFish('contains', [$method.' 說明文字']);
    viewFish('prefixed', ['x'.$method]);
    viewFish('typo', ['mamasi']);
    viewFish('empty-value', ['']);

    $props = viewProps($this->get("/?view=$view"));

    expect(viewNames($props))->toBe(['exact-trimmed']);
    expect(viewOptionCounts(viewProps($this->get('/')))[$view])->toBe(1);
})->with('夜與日');

it('同一條魚有多筆同方式紀錄只算一條，已刪除的紀錄與魚不計', function (string $view, string $method) {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('many-records', [$method, $method, $method]);
    $deletedRecord = viewFish('deleted-record', [$method]);
    $deletedRecord->captureRecords()->delete();
    $deletedFish = viewFish('deleted-fish', [$method]);
    $deletedFish->delete();

    $props = viewProps($this->get("/?view=$view"));

    expect(viewNames($props))->toBe(['many-records']);
    expect(viewOptionCounts(viewProps($this->get('/')))[$view])->toBe(1);
})->with('夜與日');

it('只有其他方式或兩種都有的魚：兩種都有的兩邊出現，只有其他方式的兩邊都不出現', function (string $view, string $method) {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('both', ['mamacik', 'mamasil']);
    viewFish('net-only', ['網魚', '船釣（拼板舟）']);
    viewFish('no-record');

    expect(viewNames(viewProps($this->get("/?view=$view"))))->toBe(['both']);
})->with('夜與日');

it('依編號新到舊分頁，所有頁合計等於選項數字', function (string $view, string $method) {
    $this->actingAs(User::factory()->lineViewer()->create());
    foreach (range(1, 5) as $i) {
        viewFish("$view-$i", [$method]);
    }
    viewFish('other', ['網魚']);

    $page1 = viewProps($this->get("/?view=$view&perPage=2"));
    $page2 = viewProps($this->get("/?view=$view&perPage=2&last_id=".$page1['pageInfo']['nextCursor']));
    $page3 = viewProps($this->get("/?view=$view&perPage=2&last_id=".$page2['pageInfo']['nextCursor']));

    expect(viewNames($page1))->toBe(["$view-5", "$view-4"]);
    expect(viewNames($page2))->toBe(["$view-3", "$view-2"]);
    expect(viewNames($page3))->toBe(["$view-1"]);
    expect($page3['pageInfo']['hasMore'])->toBeFalse();
    expect(count($page1['items']) + count($page2['items']) + count($page3['items']))
        ->toBe(viewOptionCounts(viewProps($this->get('/')))[$view]);
})->with('夜與日');

// ── 驗收 3：名字一樣 ──────────────────────────────────────────

it('名字去空白不分大小寫相同才算一組，名字空白與沒有重複的不算', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('Rahet');
    viewFish('oyod');
    viewFish(' rahet ');
    viewFish('single');
    viewFish('');
    viewFish('  ');

    $props = viewProps($this->get('/?view=same_name'));

    expect(viewNames($props))->toBe(['Rahet', ' rahet ']);
    expect(array_column($props['items'], 'group_label'))->toBe(['Rahet', 'Rahet']);
    expect(array_column($props['items'], 'group_count'))->toBe([2, 2]);
    expect(viewOptionCounts(viewProps($this->get('/')))['same_name'])->toBe(1);
});

it('段標題顯示名取該組編號最小的魚的寫法，組依名字排序，組內依編號', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    $zeta1 = viewFish(' Zeta ');
    $alpha1 = viewFish('alpha');
    $zeta2 = viewFish('ZETA');
    $alpha2 = viewFish('Alpha');

    $props = viewProps($this->get('/?view=same_name'));

    expect(array_column($props['items'], 'id'))->toBe([$alpha1->id, $alpha2->id, $zeta1->id, $zeta2->id]);
    expect(array_column($props['items'], 'group_label'))->toBe(['alpha', 'alpha', 'Zeta', 'Zeta']);
});

it('名字一樣分頁以整組為單位，一組不拆到兩頁', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    foreach (['a', 'a', 'a', 'b', 'b', 'c', 'c'] as $name) {
        viewFish($name);
    }

    $page1 = viewProps($this->get('/?view=same_name&perPage=2'));
    $last = end($page1['items']);
    $page2 = viewProps($this->get('/?view=same_name&perPage=2&last_id='.$last['id'].'&last_name='.$last['name']));
    $last2 = end($page2['items']);
    $page3 = viewProps($this->get('/?view=same_name&perPage=2&last_id='.$last2['id'].'&last_name='.$last2['name']));

    expect(viewNames($page1))->toBe(['a', 'a', 'a']);
    expect($page1['pageInfo']['hasMore'])->toBeTrue();
    expect(viewNames($page2))->toBe(['b', 'b']);
    expect(viewNames($page3))->toBe(['c', 'c']);
    expect($page3['pageInfo']['hasMore'])->toBeFalse();
});

// ── 驗收 4：字母順序 ──────────────────────────────────────────

it('字母順序依段、名字鍵、編號排序，非 a 到 z 開頭放最後的其他段', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('zebra');
    $bee2 = viewFish('Bee');
    viewFish('apple');
    viewFish('bee');
    viewFish('1fish');
    viewFish('魚');
    viewFish('école');
    viewFish('');
    viewFish('  Apple2');

    $props = viewProps($this->get('/?view=alpha'));

    expect(viewNames($props))->toBe(['apple', '  Apple2', 'Bee', 'bee', 'zebra', '', '1fish', 'école', '魚']);
    expect(array_column($props['items'], 'group_label'))
        ->toBe(['A', 'A', 'B', 'B', 'Z', '其他', '其他', '其他', '其他']);
    expect(array_column($props['items'], 'id')[2])->toBe($bee2->id);
});

it('字母段標題的魚數在第一頁一次給全部，往下載入不帶、不變', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    foreach (['a1', 'a2', 'a3', 'b1', '9x'] as $name) {
        viewFish($name);
    }

    $page1 = viewProps($this->get('/?view=alpha&perPage=2'));
    $last = end($page1['items']);
    $page2 = viewProps($this->get('/?view=alpha&perPage=2&last_id='.$last['id'].'&last_name='.$last['name']));

    expect($page1['viewGroupCounts'])->toBe([
        ['label' => 'A', 'count' => 3],
        ['label' => 'B', 'count' => 1],
        ['label' => '其他', 'count' => 1],
    ]);
    expect($page2['viewGroupCounts'] ?? null)->toBeNull();
    expect($page1['viewOptions'])->not->toBeNull();
    expect($page2['viewOptions'])->toBeNull();
});

it('字母順序跨頁不重複不漏：同名與大小寫不同、段交界、空名都跨頁', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    $bee = viewFish('bee');
    $beeCap = viewFish('Bee');
    $beeUpper = viewFish('BEE');
    $ant = viewFish('ant');
    $zoo = viewFish('zoo');
    $zooCap = viewFish('Zoo');
    $empty1 = viewFish('');
    $empty2 = viewFish('');
    $digit = viewFish('3d');
    $a = viewFish('a');

    $expectedPages = [
        [$a->id, $ant->id, $bee->id],
        [$beeCap->id, $beeUpper->id, $zoo->id],
        [$zooCap->id, $empty1->id, $empty2->id],
        [$digit->id],
    ];

    $url = '/?view=alpha&perPage=3';
    foreach ($expectedPages as $index => $expectedIds) {
        $props = viewProps($this->get($url));
        expect(array_column($props['items'], 'id'))->toBe($expectedIds);
        expect($props['pageInfo']['hasMore'])->toBe($index < count($expectedPages) - 1);
        $url = '/?view=alpha&perPage=3&last_id='.$props['pageInfo']['nextCursor'].'&last_name='.urlencode($props['pageInfo']['nextName'] ?? '');
    }
});

it('游標只給一半或格式不合法時回無效游標錯誤', function (string $query) {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('a');

    $this->getJson('/?'.$query)->assertStatus(422)->assertJson(['error' => 'INVALID_CURSOR']);
})->with([
    'alpha 只有 last_id' => 'view=alpha&last_id=1',
    'same_name 只有 last_id' => 'view=same_name&last_id=1',
    'alpha 只有 last_name' => 'view=alpha&last_name=a',
    'last_name 不是字串' => 'view=alpha&last_id=1&last_name[]=a',
    'last_id 不合法' => 'view=alpha&last_id=0&last_name=a',
]);

it('last_name 為空字串是合法游標（空名魚）', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    $first = viewFish('');
    $second = viewFish('');

    $props = viewProps($this->get('/?view=alpha&last_id='.$first->id.'&last_name='));

    expect(array_column($props['items'], 'id'))->toBe([$second->id]);
});

it('view 帶陣列或其他型別也視為全部，不報錯', function (string $query) {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('a');
    viewFish('b');

    $response = $this->get('/?'.$query);

    $response->assertStatus(200);
    expect(viewProps($response)['view'])->toBe('all');
    expect(count(viewProps($response)['items']))->toBe(2);
})->with(['view[]=night', 'view[a]=night', 'view=']);

it('無效的 view 值視為全部', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('a');
    viewFish('b');

    $props = viewProps($this->get('/?view=bogus'));

    expect($props['view'])->toBe('all');
    expect(count($props['items']))->toBe(2);
});

// ── 驗收 8：計算失敗 ──────────────────────────────────────────

it('選項計算失敗時記錄錯誤、不帶選項，清單照常顯示', function () {
    $this->actingAs(User::factory()->lineViewer()->create());
    viewFish('a');
    Log::spy();
    $this->partialMock(FishSearchService::class, function ($mock) {
        $mock->shouldReceive('getViewOptions')->andThrow(new RuntimeException('boom'));
    });

    $response = $this->get('/');

    $response->assertStatus(200);
    expect(viewProps($response)['viewOptions'])->toBeNull();
    expect(count(viewProps($response)['items']))->toBe(1);
    Log::shouldHaveReceived('error')->once();
});
