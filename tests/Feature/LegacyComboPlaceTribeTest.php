<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Fish;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a legacy combo only within its tribe', function (bool $hasLocalPlace) {
    Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => '岸邊', 'name_key' => '岸邊']);
    $local = $hasLocalPlace
        ? Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => '岸邊', 'name_key' => '岸邊'])
        : null;
    $fish = Fish::factory()->create();
    $combo = ['tribe' => 'yayo', 'location' => '岸邊', 'capture_date' => '2026-10-01', 'capture_method' => 'mamasil'];
    $old = CaptureRecord::factory()->create([...$combo, 'fish_id' => $fish->id, 'session_id' => null]);

    $this->actingAs(User::factory()->lineEditor()->create())->post("/fish/{$fish->id}/capture-records", [
        'image_filename' => 'tribe-test.jpg', 'legacy_combo' => $combo,
    ])->assertSessionDoesntHaveErrors()->assertRedirect("/fish/{$fish->id}/media-manager");

    $session = CaptureSession::sole();
    expect($session->tribe)->toBe('yayo')
        ->and($session->place_id)->toBe($local?->id)
        ->and($session->location_hint)->toBe($hasLocalPlace ? null : '岸邊')
        ->and($old->fresh()->session_id)->toBeNull()
        ->and(CaptureRecord::where('session_id', $session->id)->sole()->location)->toBe('岸邊')
        ->and(Place::count())->toBe($hasLocalPlace ? 2 : 1);
})->with(['other tribe only' => false, 'both tribes' => true]);
