<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function assignSharedTribes(): void
{
    (require database_path('migrations/2026_10_10_000001_assign_tribes_to_shared_places.php'))->up();
}

function sharedPlace(array $attributes = []): Place
{
    return Place::factory()->create(['name' => 'ZZPlace', 'name_key' => 'zzplace', 'tao_name' => 'ZZTAOMARK', 'notes' => 'Test note', 'is_provisional' => true, ...$attributes]);
}

it('reuses a shared place for two sessions and repairs stale and null record locations', function () {
    $place = sharedPlace();
    $sessions = CaptureSession::factory()->count(2)->create(['place_id' => $place->id, 'tribe' => 'iraraley']);
    $stale = CaptureRecord::factory()->create(['session_id' => $sessions[0]->id, 'location' => 'Stale']);
    $empty = CaptureRecord::factory()->create(['session_id' => $sessions[0]->id, 'location' => null]);
    assignSharedTribes();
    expect(Place::count())->toBe(1)->and($place->fresh()->tribe)->toBe('iraraley')->and($place->fresh()->scope_key)->toBe('iraraley');
    foreach ($sessions as $session) {
        expect($session->fresh()->place_id)->toBe($place->id);
    }
    expect($stale->fresh()->location)->toBe('ZZPlace')->and($empty->fresh()->location)->toBe('ZZPlace');
});

it('splits across tribes in string order and synchronizes deleted records on the clone', function () {
    $place = sharedPlace(['created_at' => '2020-01-02 03:04:05']);
    $yayo = CaptureSession::factory()->count(2)->create(['place_id' => $place->id, 'tribe' => 'yayo']);
    $iraraley = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'iraraley']);
    $deleted = CaptureRecord::factory()->create(['session_id' => $yayo[0]->id, 'location' => 'Stale', 'deleted_at' => now()]);
    assignSharedTribes();
    $clone = Place::where('tribe', 'yayo')->sole();
    expect(Place::count())->toBe(2)->and($place->fresh()->tribe)->toBe('iraraley')
        ->and($iraraley->fresh()->place_id)->toBe($place->id)
        ->and($clone->only(['scope_key', 'name', 'name_key', 'tao_name', 'notes', 'is_provisional']))->toBe([
            'scope_key' => 'yayo', 'name' => 'ZZPlace', 'name_key' => 'zzplace', 'tao_name' => 'ZZTAOMARK', 'notes' => 'Test note', 'is_provisional' => true,
        ])->and($clone->created_at->format('Y-m-d H:i:s'))->toBe('2020-01-02 03:04:05')
        ->and($deleted->fresh()->location)->toBe('ZZPlace')->and($deleted->fresh()->trashed())->toBeTrue();
    foreach ($yayo as $session) {
        expect($session->fresh()->place_id)->toBe($clone->id);
    }
});

it('merges into existing names and fills only blank metadata', function (?string $tao, ?string $notes, string $expectedNotes) {
    $place = sharedPlace();
    $target = sharedPlace(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => 'zzplace', 'tao_name' => $tao, 'notes' => $notes, 'is_provisional' => false, 'updated_at' => '2020-01-01 00:00:00']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'yayo']);
    $live = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => 'ZZPlace']);
    $deleted = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => null, 'deleted_at' => now()]);
    assignSharedTribes();
    expect(Place::count())->toBe(1)->and($place->fresh())->toBeNull()->and($session->fresh()->place_id)->toBe($target->id)
        ->and($live->fresh()->location)->toBe('zzplace')->and($deleted->fresh()->location)->toBe('zzplace')
        ->and($target->fresh()->tao_name)->toBe('ZZTAOMARK')->and($target->fresh()->notes)->toBe($expectedNotes)
        ->and($target->fresh()->is_provisional)->toBeFalse()->and($target->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2020-01-01 00:00:00');
})->with([[null, null, 'Test note'], ['', null, 'Test note'], ["　 \t", 'Existing', 'Existing'], ['　', 'Existing', 'Existing']]);

it('deletes unused shared places', function () {
    sharedPlace();
    assignSharedTribes();
    expect(Place::count())->toBe(0);
});

it('retains a place used exclusively by soft deleted records', function () {
    $place = sharedPlace();
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'iraraley']);
    $record = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => 'Stale', 'deleted_at' => now()]);
    assignSharedTribes();
    expect($place->fresh()->tribe)->toBe('iraraley')->and($session->fresh()->place_id)->toBe($place->id)
        ->and($record->fresh()->location)->toBe('ZZPlace')->and($record->fresh()->trashed())->toBeTrue();
});

it('preserves totals and tribe consistency with mixed existing reuse and split branches', function () {
    $place = sharedPlace();
    sharedPlace(['name' => 'Unused', 'name_key' => 'unused']);
    $target = sharedPlace(['tribe' => 'iraraley', 'scope_key' => 'iraraley']);
    foreach (['yayo', 'iraraley', 'ivalino', 'unknown-tribe'] as $tribe) {
        $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => $tribe]);
        CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => null, 'deleted_at' => $tribe === 'yayo' ? now() : null]);
    }
    assignSharedTribes();
    expect(Place::whereNull('tribe')->count())->toBe(0)->and(Place::count())->toBe(4)
        ->and(CaptureSession::count())->toBe(4)->and(CaptureRecord::withTrashed()->count())->toBe(4)
        ->and($place->fresh()->tribe)->toBe('ivalino')
        ->and(CaptureSession::where('tribe', 'iraraley')->sole()->place_id)->toBe($target->id);
    foreach (CaptureSession::with('place')->get() as $session) {
        expect($session->place->tribe)->toBe($session->tribe);
    }
    foreach (CaptureRecord::withTrashed()->get() as $record) {
        expect($record->location)->toBe('ZZPlace');
    }
});

it('leaves scoped places sessions and even stale record locations untouched', function () {
    $place = sharedPlace(['tribe' => 'yayo', 'scope_key' => 'yayo']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'yayo']);
    $record = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => 'Stale']);
    sharedPlace(['name' => 'Unused', 'name_key' => 'unused']);
    $before = [$place->fresh()->getAttributes(), $session->fresh()->getAttributes(), $record->fresh()->getAttributes()];
    assignSharedTribes();
    expect([$place->fresh()->getAttributes(), $session->fresh()->getAttributes(), $record->fresh()->getAttributes()])->toBe($before);
});

it('is a no op on empty data and on repeated execution', function () {
    assignSharedTribes();
    expect(Place::count())->toBe(0);
    $place = sharedPlace();
    CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'iraraley']);
    assignSharedTribes();
    $before = [DB::table('places')->get()->toJson(), DB::table('capture_sessions')->get()->toJson()];
    assignSharedTribes();
    expect([DB::table('places')->get()->toJson(), DB::table('capture_sessions')->get()->toJson()])->toBe($before);
});

it('rolls back all conversion writes if record synchronization fails', function () {
    $place = sharedPlace();
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'iraraley']);
    CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => 'Stale']);
    DB::listen(function ($query): void {
        if (str_starts_with(strtolower(str_replace(['`', '"'], '', $query->sql)), 'update capture_records')) {
            throw new RuntimeException('forced synchronization failure');
        }
    });
    expect(fn () => assignSharedTribes())->toThrow(RuntimeException::class, 'forced synchronization failure');
    expect($place->fresh()->tribe)->toBeNull()->and($session->fresh()->place_id)->toBe($place->id)
        ->and(CaptureRecord::sole()->location)->toBe('Stale');
});
