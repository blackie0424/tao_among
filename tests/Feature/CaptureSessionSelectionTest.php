<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Fish;
use App\Models\Place;
use App\Models\User;
use App\Services\CaptureSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns selectable sessions with fixed display semantics and live record counts', function () {
    $place = Place::factory()->create(['name' => '測試灣', 'name_key' => '測試灣']);
    $older = CaptureSession::factory()->create(['capture_date' => '2026-10-01', 'location_hint' => null]);
    $newer = CaptureSession::factory()->create(['capture_date' => '2026-10-02', 'place_id' => $place->id]);
    CaptureRecord::factory()->count(2)->create(['session_id' => $newer->id]);
    $deleted = CaptureRecord::factory()->create(['session_id' => $newer->id]);
    $deleted->delete();

    $sessions = app(CaptureSessionService::class)->getSelectableSessions();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['id'])->toBe($newer->id)
        ->and($sessions[0]['place_name'])->toBe('測試灣')
        ->and($sessions[0]['record_count'])->toBe(2)
        ->and($sessions[1]['id'])->toBe($older->id)
        ->and($sessions[1]['record_count'])->toBe(0);
});

it('canonicalizes legacy locations and excludes equivalent real sessions', function () {
    $fish = Fish::factory()->create();
    foreach ([null, '', '   ', '　', '待補充'] as $location) {
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'session_id' => null,
            'capture_date' => '2026-10-01',
            'tribe' => 'ivalino',
            'capture_method' => 'mamasil',
            'location' => $location,
        ]);
    }
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'session_id' => null,
        'capture_date' => '2026-10-02',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location' => 'LINE Bot',
    ]);

    $combos = app(CaptureSessionService::class)->getLegacyCombos();
    expect($combos)->toHaveCount(1)
        ->and($combos[0]['location'])->toBeNull()
        ->and($combos[0]['record_count'])->toBe(5);

    CaptureSession::factory()->create([
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location_hint' => null,
    ]);
    expect(app(CaptureSessionService::class)->getLegacyCombos())->toBe([]);
});

it('creates records from the latest session values and ignores client context fields', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create([
        'capture_date' => '2026-10-03',
        'tribe' => 'yayo',
        'capture_method' => '船釣（拼板舟）',
        'location_hint' => '外海',
    ]);

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'session.jpg',
        'session_id' => $session->id,
        'notes' => '固定備註',
    ])->assertRedirect("/fish/{$fish->id}/media-manager");

    $record = CaptureRecord::where('fish_id', $fish->id)->firstOrFail();
    expect($record->session_id)->toBe($session->id)
        ->and($record->tribe)->toBe('yayo')
        ->and($record->location)->toBe('外海')
        ->and($record->capture_method)->toBe('船釣（拼板舟）')
        ->and($record->capture_date->format('Y-m-d'))->toBe('2026-10-03');
});

it('rejects missing, conflicting, and client supplied context fields', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create();
    $combo = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => 'mamasil', 'location' => null];

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", ['image_filename' => 'a.jpg'])
        ->assertSessionHasErrors(['session_id', 'legacy_combo']);
    $this->post("/fish/{$fish->id}/capture-records", ['image_filename' => 'a.jpg', 'session_id' => $session->id, 'legacy_combo' => $combo])
        ->assertSessionHasErrors(['session_id', 'legacy_combo']);
    $this->post("/fish/{$fish->id}/capture-records", ['image_filename' => 'a.jpg', 'session_id' => $session->id, 'tribe' => 'yayo'])
        ->assertSessionHasErrors('tribe');
    expect(CaptureRecord::count())->toBe(0);
});

it('creates and sequentially reuses a session from a verified legacy combo without changing old records', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $old = CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'session_id' => null,
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location' => '  測試　灣  ',
    ]);
    $place = Place::factory()->create(['name' => '測試 灣', 'name_key' => '測試 灣']);
    $combo = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => 'mamasil', 'location' => '測試　灣'];

    foreach (['new-a.jpg', 'new-b.jpg'] as $filename) {
        $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
            'image_filename' => $filename,
            'legacy_combo' => $combo,
        ])->assertSessionDoesntHaveErrors();
    }

    expect(CaptureSession::count())->toBe(1)
        ->and(CaptureSession::first()->place_id)->toBe($place->id)
        ->and(CaptureRecord::whereNotNull('session_id')->count())->toBe(2)
        ->and($old->fresh()->session_id)->toBeNull()
        ->and($old->fresh()->location)->toBe('  測試　灣  ');
});

it('rejects a forged or stale legacy combo without writing data', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $combo = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => 'mamasil', 'location' => '不存在'];

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'fake.jpg',
        'legacy_combo' => $combo,
    ])->assertSessionHasErrors('legacy_combo');

    expect(CaptureSession::count())->toBe(0)
        ->and(CaptureRecord::count())->toBe(0);
});

it('keeps linked context immutable and keeps legacy records editable', function () {
    $editor = User::factory()->lineEditor()->create();
    $fishA = Fish::factory()->create();
    $fishB = Fish::factory()->create();
    $session = CaptureSession::factory()->create();
    $linked = CaptureRecord::factory()->create(['fish_id' => $fishA->id, 'session_id' => $session->id, 'tribe' => 'ivalino']);
    $legacy = CaptureRecord::factory()->create(['fish_id' => $fishA->id, 'session_id' => null]);

    $this->actingAs($editor)->put("/fish/{$fishA->id}/capture-records/{$linked->id}", ['tribe' => 'yayo', 'notes' => '新備註'])
        ->assertSessionHasErrors('tribe');
    expect($linked->fresh()->tribe)->toBe('ivalino');

    $this->put("/fish/{$fishA->id}/capture-records/{$linked->id}", ['notes' => '新備註', 'image_scale' => 1.2])
        ->assertRedirect();
    expect($linked->fresh()->notes)->toBe('新備註');

    $this->put("/fish/{$fishA->id}/capture-records/{$legacy->id}", [
        'tribe' => 'yayo',
        'location' => '新地點',
        'capture_method' => 'mamasil',
        'capture_date' => '2026-10-01',
        'notes' => null,
    ])->assertRedirect();
    expect($legacy->fresh()->tribe)->toBe('yayo');

    $this->put("/fish/{$fishB->id}/capture-records/{$linked->id}", ['notes' => '探測'])
        ->assertNotFound();
});

it('renders selection props and linked edit context', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create();
    $record = CaptureRecord::factory()->create(['fish_id' => $fish->id, 'session_id' => $session->id]);

    $this->actingAs($editor)->get("/fish/{$fish->id}/capture-records/batch-create")
        ->assertInertia(fn (Assert $page) => $page
            ->component('BatchCreateCaptureRecord')
            ->has('selectable_sessions', 1)
            ->has('legacy_combos', 0));
    $this->get("/fish/{$fish->id}/capture-records/{$record->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where('record.session_id', $session->id));
});

it('keeps selectable and legacy query counts fixed as rows grow', function () {
    $service = app(CaptureSessionService::class);
    CaptureSession::factory()->create();
    CaptureRecord::factory()->create(['session_id' => null, 'location' => '舊地點']);

    $phase = 'one';
    $counts = ['one' => 0, 'many' => 0];
    DB::listen(function ($query) use (&$phase, &$counts): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (str_contains($sql, 'capture_sessions') || str_contains($sql, 'capture_records') || str_contains($sql, 'places')) {
            $counts[$phase]++;
        }
    });
    $service->getSelectableSessions();
    $service->getLegacyCombos();

    $phase = 'setup';
    $counts['setup'] = 0;
    CaptureSession::factory()->count(19)->create();
    CaptureRecord::factory()->count(19)->create(['session_id' => null]);
    $phase = 'many';
    $service->getSelectableSessions();
    $service->getLegacyCombos();

    expect($counts['one'])->toBe(3)
        ->and($counts['many'])->toBe(3);
});
it('limits selectable sessions to twenty and uses id as the same-date tiebreak', function () {
    $sessions = CaptureSession::factory()->count(21)->create(['capture_date' => '2026-10-05']);

    $selectable = app(CaptureSessionService::class)->getSelectableSessions();

    expect($selectable)->toHaveCount(20)
        ->and($selectable[0]['id'])->toBe($sessions->last()->id)
        ->and(collect($selectable)->pluck('id')->all())->not->toContain($sessions->first()->id);
});

it('stores null location when a session has neither place nor hint', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $session = CaptureSession::factory()->create(['place_id' => null, 'location_hint' => null]);

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'no-place.jpg',
        'session_id' => $session->id,
    ])->assertRedirect();

    expect(CaptureRecord::where('fish_id', $fish->id)->firstOrFail()->location)->toBeNull();
});

it('rejects a disappeared legacy combo but reuses an existing equivalent session', function () {
    $editor = User::factory()->lineEditor()->create();
    $fish = Fish::factory()->create();
    $old = CaptureRecord::factory()->create([
        'session_id' => null,
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location' => '測試灣',
    ]);
    $combo = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => 'mamasil', 'location' => '測試灣'];
    $old->delete();

    $this->actingAs($editor)->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'stale.jpg',
        'legacy_combo' => $combo,
    ])->assertSessionHasErrors('legacy_combo');
    expect(CaptureSession::count())->toBe(0)->and(CaptureRecord::count())->toBe(0);

    $session = CaptureSession::factory()->create([
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location_hint' => '測試灣',
    ]);
    $this->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'reuse.jpg',
        'legacy_combo' => $combo,
    ])->assertRedirect();

    expect(CaptureSession::count())->toBe(1)
        ->and(CaptureRecord::firstOrFail()->session_id)->toBe($session->id);
});

it('rolls back fish creation when session resolution fails after request validation', function () {
    $editor = User::factory()->lineEditor()->create();
    $session = CaptureSession::factory()->create();
    $service = Mockery::mock(\App\Services\CaptureRecordBatchService::class);
    $service->shouldReceive('createForFishFromSession')
        ->once()
        ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
            'session_id' => '這個情境已不存在，請重新選擇',
        ]));
    app()->instance(\App\Services\CaptureRecordBatchService::class, $service);

    $this->actingAs($editor)->post('/fish/batch-create', [
        'name' => '不應建立',
        'filenames' => ['rollback.jpg'],
        'session_id' => $session->id,
    ])->assertSessionHasErrors('session_id');

    expect(Fish::where('name', '不應建立')->count())->toBe(0)
        ->and(CaptureRecord::count())->toBe(0);
});
