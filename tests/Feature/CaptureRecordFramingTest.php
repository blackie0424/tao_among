<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const FRAMING_FIELDS = ['image_focus_x', 'image_focus_y', 'image_zoom', 'image_rotation'];

function framingRecord(array $attributes = []): array
{
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $record = CaptureRecord::factory()->create(array_merge([
        'fish_id' => $fish->id,
        'session_id' => CaptureSession::factory()->create()->id,
    ], $attributes));

    return [$editor, $fish, $record];
}

function framingUrl(Fish $fish, CaptureRecord $record): string
{
    return "/fish/{$fish->id}/capture-records/{$record->id}";
}

// ── AC 1：資料與驗證 ───────────────────────────────────────

it('capture_records 有四個新欄位', function () {
    expect(Schema::hasColumns('capture_records', FRAMING_FIELDS))->toBeTrue();
});

it('四個值都合法時存入', function () {
    [$editor, $fish, $record] = framingRecord();

    $this->actingAs($editor)->put(framingUrl($fish, $record), [
        'image_focus_x' => 30.5, 'image_focus_y' => 60, 'image_zoom' => 1.5, 'image_rotation' => 90,
    ])->assertRedirect();

    $fresh = $record->fresh();
    expect((float) $fresh->image_focus_x)->toBe(30.5)
        ->and((float) $fresh->image_focus_y)->toBe(60.0)
        ->and((float) $fresh->image_zoom)->toBe(1.5)
        ->and($fresh->image_rotation)->toBe(90);
});

it('只給其中幾個值一律驗證失敗且不存', function (array $payload, string $errorKey) {
    [$editor, $fish, $record] = framingRecord();

    $this->actingAs($editor)->put(framingUrl($fish, $record), $payload)->assertSessionHasErrors($errorKey);

    expect($record->fresh()->image_focus_x)->toBeNull()
        ->and($record->fresh()->image_rotation)->toBeNull();
})->with([
    '只有旋轉' => [['image_rotation' => 90], 'image_focus_x'],
    '只有放大' => [['image_zoom' => 1.5], 'image_focus_x'],
    '有中心沒放大' => [['image_focus_x' => 50, 'image_focus_y' => 50, 'image_rotation' => 0], 'image_zoom'],
    '只有橫向' => [['image_focus_x' => 50, 'image_zoom' => 1, 'image_rotation' => 0], 'image_focus_y'],
    '有值但旋轉是 null' => [['image_focus_x' => 50, 'image_focus_y' => 50, 'image_zoom' => 1, 'image_rotation' => null], 'image_rotation'],
]);

it('已有完整值時只送部分欄位（含 null、空字串）驗證失敗，四個值不變', function (array $payload) {
    [$editor, $fish, $record] = framingRecord([
        'image_focus_x' => 20, 'image_focus_y' => 30, 'image_zoom' => 2, 'image_rotation' => 90,
    ]);

    $this->actingAs($editor)->put(framingUrl($fish, $record), $payload)->assertSessionHasErrors();

    $fresh = $record->fresh();
    expect($fresh->framing)->toBe(['x' => 20.0, 'y' => 30.0, 'zoom' => 2.0, 'rotation' => 90]);
})->with([
    '只送橫向 null' => [['image_focus_x' => null]],
    '只送橫向空字串' => [['image_focus_x' => '']],
    '四個欄位裡三個 null' => [['image_focus_x' => null, 'image_focus_y' => null, 'image_zoom' => null]],
    '只送旋轉 null' => [['image_rotation' => null]],
]);

it('超出範圍或旋轉不是 0/90/180/270 驗證失敗', function (string $field, mixed $value) {
    [$editor, $fish, $record] = framingRecord();
    $payload = ['image_focus_x' => 50, 'image_focus_y' => 50, 'image_zoom' => 1.5, 'image_rotation' => 0];
    $payload[$field] = $value;

    $this->actingAs($editor)->put(framingUrl($fish, $record), $payload)->assertSessionHasErrors($field);
    expect($record->fresh()->image_zoom)->toBeNull();
})->with([
    ['image_focus_x', 100.5], ['image_focus_x', -1], ['image_focus_y', 101],
    ['image_zoom', 0.9], ['image_zoom', 3.1],
    ['image_rotation', 45], ['image_rotation', 360], ['image_rotation', -90],
]);

it('邊界值 0、100、1.0、3.0、270 合法', function () {
    [$editor, $fish, $record] = framingRecord();

    $this->actingAs($editor)->put(framingUrl($fish, $record), [
        'image_focus_x' => 0, 'image_focus_y' => 100, 'image_zoom' => 3.0, 'image_rotation' => 270,
    ])->assertSessionHasNoErrors();

    expect($record->fresh()->image_rotation)->toBe(270);
});

it('沒有情境的舊紀錄更新同樣驗證四個值', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $legacy = CaptureRecord::factory()->create(['fish_id' => $fish->id, 'session_id' => null]);
    $base = [
        'tribe' => 'yayo', 'location' => '地點', 'capture_method' => 'mamasil', 'capture_date' => '2026-10-01',
    ];

    $this->actingAs($editor)->put(framingUrl($fish, $legacy), $base + ['image_zoom' => 2])
        ->assertSessionHasErrors('image_focus_x');
    $this->put(framingUrl($fish, $legacy), $base + [
        'image_focus_x' => 10, 'image_focus_y' => 20, 'image_zoom' => 2, 'image_rotation' => 180,
    ])->assertSessionHasNoErrors();

    expect($legacy->fresh()->image_rotation)->toBe(180);
});

// ── AC 2：儲存與還原 ───────────────────────────────────────

it('完全沒帶四個欄位時不動現有值', function () {
    [$editor, $fish, $record] = framingRecord([
        'image_focus_x' => 20, 'image_focus_y' => 30, 'image_zoom' => 2, 'image_rotation' => 90,
    ]);

    $this->actingAs($editor)->put(framingUrl($fish, $record), ['notes' => '只改備註'])->assertRedirect();

    $fresh = $record->fresh();
    expect($fresh->notes)->toBe('只改備註')
        ->and((float) $fresh->image_zoom)->toBe(2.0)
        ->and($fresh->image_rotation)->toBe(90);
});

it('明確帶四個 null 是還原', function () {
    [$editor, $fish, $record] = framingRecord([
        'image_focus_x' => 20, 'image_focus_y' => 30, 'image_zoom' => 2, 'image_rotation' => 90,
    ]);

    $this->actingAs($editor)->put(framingUrl($fish, $record), [
        'image_focus_x' => null, 'image_focus_y' => null, 'image_zoom' => null, 'image_rotation' => null,
    ])->assertSessionHasNoErrors();

    $fresh = $record->fresh();
    expect([$fresh->image_focus_x, $fresh->image_focus_y, $fresh->image_zoom, $fresh->image_rotation])
        ->each->toBeNull();
});

it('更換照片時清空四個值，不論請求有沒有帶', function (array $extra) {
    [$editor, $fish, $record] = framingRecord([
        'image_path' => 'old.jpg',
        'image_focus_x' => 20, 'image_focus_y' => 30, 'image_zoom' => 2, 'image_rotation' => 90,
    ]);

    $this->actingAs($editor)->put(framingUrl($fish, $record), ['image_filename' => 'new.jpg'] + $extra)
        ->assertSessionHasNoErrors();

    $fresh = $record->fresh();
    expect($fresh->image_path)->toBe('new.jpg')
        ->and([$fresh->image_focus_x, $fresh->image_focus_y, $fresh->image_zoom, $fresh->image_rotation])
        ->each->toBeNull();
})->with([
    '沒帶' => [[]],
    '帶了新值' => [['image_focus_x' => 5, 'image_focus_y' => 5, 'image_zoom' => 1.2, 'image_rotation' => 270]],
    '帶了還原' => [['image_focus_x' => null, 'image_focus_y' => null, 'image_zoom' => null, 'image_rotation' => null]],
]);

it('建立新紀錄不接受四個欄位，一律存空', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create();

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'a.jpg', 'session_id' => $session->id,
        'image_focus_x' => 10, 'image_focus_y' => 10, 'image_zoom' => 2, 'image_rotation' => 90,
    ])->assertRedirect();

    $record = CaptureRecord::where('fish_id', $fish->id)->firstOrFail();
    expect([$record->image_focus_x, $record->image_focus_y, $record->image_zoom, $record->image_rotation])
        ->each->toBeNull();
});

// ── AC 3：framing 與照片同源 ───────────────────────────────

it('CaptureRecord framing：四者皆空是 null，皆有值是四個欄位', function () {
    $empty = CaptureRecord::factory()->create();
    $set = CaptureRecord::factory()->create([
        'image_focus_x' => 30.5, 'image_focus_y' => 60, 'image_zoom' => 1.5, 'image_rotation' => 90,
    ]);

    expect($empty->fresh()->framing)->toBeNull()
        ->and($set->fresh()->framing)->toBe(['x' => 30.5, 'y' => 60.0, 'zoom' => 1.5, 'rotation' => 90]);
});

function fishWithDisplayRecord(array $recordAttributes = []): array
{
    $fish = Fish::factory()->create(['image' => 'own.jpg']);
    $record = CaptureRecord::factory()->create(array_merge([
        'fish_id' => $fish->id,
        'image_path' => 'record.jpg',
        'image_focus_x' => 30, 'image_focus_y' => 60, 'image_zoom' => 1.5, 'image_rotation' => 90,
    ], $recordAttributes));
    $fish->update(['display_capture_record_id' => $record->id]);

    return [$fish, $record];
}

it('主圖紀錄有值且關聯已載入：照片與 framing 都來自主圖紀錄', function () {
    [$fish, $record] = fishWithDisplayRecord();
    $loaded = Fish::with('displayCaptureRecord')->find($fish->id);

    expect($loaded->display_image_url)->toBe($record->image_url)
        ->and($loaded->display_image_framing)->toBe(['x' => 30.0, 'y' => 60.0, 'zoom' => 1.5, 'rotation' => 90]);
});

it('關聯沒載入：照片回退魚本身，framing 是 null', function () {
    [$fish] = fishWithDisplayRecord();
    $notLoaded = Fish::find($fish->id);

    expect($notLoaded->display_image_url)->toBe($notLoaded->image_url)
        ->and($notLoaded->display_image_framing)->toBeNull();
});

it('主圖紀錄被軟刪除：照片回退魚本身，framing 是 null', function () {
    [$fish, $record] = fishWithDisplayRecord();
    $record->delete();
    $loaded = Fish::with('displayCaptureRecord')->find($fish->id);

    expect($loaded->display_image_url)->toBe($loaded->image_url)
        ->and($loaded->display_image_framing)->toBeNull();
});

it('沒有主圖紀錄：framing 是 null', function () {
    $fish = Fish::factory()->create();
    $loaded = Fish::with('displayCaptureRecord')->find($fish->id);

    expect($loaded->display_image_framing)->toBeNull();
});

it('主圖紀錄沒調整過：framing 是 null', function () {
    [$fish] = fishWithDisplayRecord([
        'image_focus_x' => null, 'image_focus_y' => null, 'image_zoom' => null, 'image_rotation' => null,
    ]);

    expect(Fish::with('displayCaptureRecord')->find($fish->id)->display_image_framing)->toBeNull();
});

it('編輯頁帶出這筆紀錄的調整值', function () {
    [$editor, $fish, $record] = framingRecord([
        'image_focus_x' => 30, 'image_focus_y' => 60, 'image_zoom' => 1.5, 'image_rotation' => 90,
    ]);

    $this->actingAs($editor)->get(framingUrl($fish, $record).'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EditCaptureRecord')
            ->where('record.framing', ['x' => 30, 'y' => 60, 'zoom' => 1.5, 'rotation' => 90])
        );
});

// ── AC 6：各頁的魚都帶主圖與同一張圖的調整值 ─────────────────

it('批次新增頁、部落分類管理頁、搜尋頁的魚帶主圖與同一張圖的調整值', function (string $url, string $path) {
    [$fish] = fishWithDisplayRecord();
    $editor = User::factory()->lineEditor()->create();
    $expected = ['x' => 30, 'y' => 60, 'zoom' => 1.5, 'rotation' => 90];
    $url = str_replace('{id}', (string) $fish->id, $url);

    $this->actingAs($editor)->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where($path, $expected));
})->with([
    '批次新增捕獲紀錄' => ['/fish/{id}/capture-records/batch-create', 'fish.display_image_framing'],
    '部落分類管理' => ['/fish/{id}/tribal-classifications/create', 'fish.display_image_framing'],
    '搜尋' => ['/search?name=', 'fishs.0.display_image_framing'],
]);
