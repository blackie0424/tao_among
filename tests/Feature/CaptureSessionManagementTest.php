<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use App\Models\User;
use App\Services\CaptureSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns fixed legacy record attributes for place hint and empty locations', function () {
    $service = app(CaptureSessionService::class);
    $place = Place::factory()->create(['name' => '東清灣', 'name_key' => '東清灣']);
    $base = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => '釣魚'];

    $withPlace = CaptureSession::factory()->create([...$base, 'place_id' => $place->id, 'location_hint' => '舊字']);
    $withHint = CaptureSession::factory()->create([...$base, 'location_hint' => '舊字']);
    $empty = CaptureSession::factory()->create([...$base, 'location_hint' => null]);

    expect($service->recordAttributes($withPlace))->toBe(['tribe' => 'ivalino', 'capture_method' => '釣魚', 'capture_date' => '2026-10-01', 'location' => '東清灣'])
        ->and($service->recordAttributes($withHint))->toBe(['tribe' => 'ivalino', 'capture_method' => '釣魚', 'capture_date' => '2026-10-01', 'location' => '舊字'])
        ->and($service->recordAttributes($empty))->toBe(['tribe' => 'ivalino', 'capture_method' => '釣魚', 'capture_date' => '2026-10-01', 'location' => null]);
});

it('synchronizes live and soft deleted records when a session changes', function () {
    $session = CaptureSession::factory()->create();
    $live = CaptureRecord::factory()->create(['session_id' => $session->id]);
    $deleted = CaptureRecord::factory()->create(['session_id' => $session->id]);
    $deleted->delete();
    $place = Place::factory()->create(['name' => '朗島灣', 'name_key' => '朗島灣']);

    app(CaptureSessionService::class)->update($session, [
        'capture_date' => '2026-10-02', 'tribe' => 'yayo', 'capture_method' => '船釣（拼板舟）', 'place_id' => $place->id, 'notes' => null,
    ]);

    expect($live->fresh()->only(['tribe', 'capture_method', 'location']))->toBe(['tribe' => 'yayo', 'capture_method' => '船釣（拼板舟）', 'location' => '朗島灣'])
        ->and($deleted->fresh()->only(['tribe', 'capture_method', 'location']))->toBe(['tribe' => 'yayo', 'capture_method' => '船釣（拼板舟）', 'location' => '朗島灣']);
});

it('rolls back the session and every record when synchronization fails', function () {
    $session = CaptureSession::factory()->create([
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => '釣魚',
    ]);
    $record = CaptureRecord::factory()->create([
        'session_id' => $session->id,
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => '釣魚',
    ]);

    DB::listen(function ($query): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (str_starts_with($sql, 'update capture_records')) {
            throw new RuntimeException('forced synchronization failure');
        }
    });

    expect(fn () => app(CaptureSessionService::class)->update($session, [
        'capture_date' => '2026-10-02',
        'tribe' => 'yayo',
        'capture_method' => '船釣（拼板舟）',
        'place_id' => null,
        'notes' => null,
    ]))->toThrow(RuntimeException::class, 'forced synchronization failure');

    expect($session->fresh()->only(['tribe', 'capture_method']))
        ->toBe(['tribe' => 'ivalino', 'capture_method' => '釣魚'])
        ->and($record->fresh()->only(['tribe', 'capture_method']))
        ->toBe(['tribe' => 'ivalino', 'capture_method' => '釣魚']);
});

it('shows live counts separately from deletion eligibility', function () {
    $editor = User::factory()->lineEditor()->create();
    $empty = CaptureSession::factory()->create(['capture_date' => '2026-10-03']);
    $softOnly = CaptureSession::factory()->create(['capture_date' => '2026-10-02']);
    $softRecord = CaptureRecord::factory()->create(['session_id' => $softOnly->id]);
    $softRecord->delete();
    $live = CaptureSession::factory()->create(['capture_date' => '2026-10-01']);
    CaptureRecord::factory()->count(2)->create(['session_id' => $live->id]);

    $this->actingAs($editor)->get('/capture-sessions')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('CaptureSessions/Index')
        ->where('sessions.data.0.id', $empty->id)->where('sessions.data.0.record_count', 0)->where('sessions.data.0.can_delete', true)
        ->where('sessions.data.1.id', $softOnly->id)->where('sessions.data.1.record_count', 0)->where('sessions.data.1.can_delete', false)
        ->where('sessions.data.2.id', $live->id)->where('sessions.data.2.record_count', 2)->where('sessions.data.2.can_delete', false));
});

it('rejects deletion when even a soft deleted record references the session', function () {
    $editor = User::factory()->lineEditor()->create();
    $session = CaptureSession::factory()->create();
    $record = CaptureRecord::factory()->create(['session_id' => $session->id]);
    $record->delete();

    $this->actingAs($editor)->delete("/capture-sessions/{$session->id}")->assertSessionHasErrors('session');
    expect($session->fresh())->not->toBeNull();
});

it('renders the create page and returns the created session as JSON', function () {
    $editor = User::factory()->lineEditor()->create();
    $payload = [
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => '釣魚',
        'place_id' => null,
        'notes' => null,
    ];

    $this->actingAs($editor)->get('/capture-sessions/create')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CaptureSessions/Create'));
    $this->postJson('/capture-sessions', $payload)->assertCreated()
        ->assertJsonPath('session.capture_method', '釣魚')
        ->assertJsonPath('session.place', null);
});

it('allows editors to create sessions and blocks viewers', function () {
    $payload = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => '釣魚', 'place_id' => null, 'notes' => null];
    $this->actingAs(User::factory()->lineViewer()->create())->post('/capture-sessions', $payload)->assertForbidden();
    $this->actingAs(User::factory()->lineEditor()->create())->post('/capture-sessions', $payload)->assertRedirect('/capture-sessions');
    $this->assertDatabaseHas('capture_sessions', ['tribe' => 'ivalino', 'capture_method' => '釣魚']);
});

it('keeps session list relation query counts fixed as rows increase', function () {
    $editor = User::factory()->lineEditor()->create();
    $create = function (int $index): void {
        $place = Place::factory()->create(['name' => "地名 {$index}", 'name_key' => "地名 {$index}"]);
        $session = CaptureSession::factory()->create(['place_id' => $place->id]);
        CaptureRecord::factory()->count(2)->create(['session_id' => $session->id]);
        $deleted = CaptureRecord::factory()->create(['session_id' => $session->id]);
        $deleted->delete();
    };
    $create(1);

    $phase = 'baseline';
    $counts = ['baseline' => ['places' => 0, 'records' => 0], 'expanded' => ['places' => 0, 'records' => 0]];
    DB::listen(function ($query) use (&$phase, &$counts): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (str_contains($sql, ' from places')) {
            $counts[$phase]['places']++;
        }
        if (str_contains($sql, ' from capture_records')) {
            $counts[$phase]['records']++;
        }
    });

    $this->actingAs($editor)->get('/capture-sessions')->assertOk();
    $create(2);
    $create(3);
    $create(4);
    $phase = 'expanded';
    $this->get('/capture-sessions')->assertOk();

    expect($counts['baseline'])->toBe(['places' => 1, 'records' => 1])
        ->and($counts['expanded'])->toBe(['places' => 1, 'records' => 1]);
});
