<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use App\Models\User;
use App\Services\PlaceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('enforces scoped place uniqueness and keeps scope key derived from tribe', function () {
    Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => 'A B', 'name_key' => 'a b']);
    Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => 'A B', 'name_key' => 'a b']);
    Place::factory()->create(['tribe' => null, 'scope_key' => '', 'name' => 'A B', 'name_key' => 'a b']);

    expect(fn () => Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name_key' => 'a b']))->toThrow(QueryException::class)
        ->and(fn () => Place::factory()->create(['tribe' => null, 'scope_key' => '', 'name_key' => 'a b']))->toThrow(QueryException::class)
        ->and(PlaceService::scopeKey(null))->toBe('')
        ->and(PlaceService::scopeKey('ivalino'))->toBe('ivalino');
});

it('resolves typed names using tribe first shared fallback and creates provisional places', function () {
    $editor = User::factory()->lineEditor()->create();
    $shared = Place::factory()->create(['name' => '岸邊', 'name_key' => '岸邊']);
    $local = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '岸邊', 'name_key' => '岸邊']);
    $payload = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => '釣魚', 'notes' => null];

    $this->actingAs($editor)->postJson('/capture-sessions', [...$payload, 'place_name' => '岸邊'])
        ->assertCreated()->assertJsonPath('session.place_id', $local->id);
    $this->postJson('/capture-sessions', [...$payload, 'tribe' => 'yayo', 'place_name' => '岸邊'])
        ->assertCreated()->assertJsonPath('session.place_id', $shared->id);
    $response = $this->postJson('/capture-sessions', [...$payload, 'place_name' => '  新　地名  '])->assertCreated();
    $created = Place::findOrFail($response->json('session.place_id'));
    expect($created->only(['tribe', 'scope_key', 'name', 'is_provisional']))
        ->toBe(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '新 地名', 'is_provisional' => true]);
});

it('rejects conflicting place inputs and cross tribe place ids', function () {
    $editor = User::factory()->lineEditor()->create();
    $other = Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo']);
    $payload = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => '釣魚', 'notes' => null];

    $this->actingAs($editor)->postJson('/capture-sessions', [...$payload, 'place_id' => $other->id, 'place_name' => '文字'])->assertUnprocessable();
    $this->post('/capture-sessions', [...$payload, 'place_id' => $other->id])->assertSessionHasErrors(['place_id' => '這個地名不屬於所選部落']);
});

it('filters and orders suggestions by tribe with local before shared', function () {
    $editor = User::factory()->lineEditor()->create();
    $shared = Place::factory()->create(['name' => '岸邊', 'name_key' => '岸邊']);
    $local = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '岸邊', 'name_key' => '岸邊']);
    $other = Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => '岸邊', 'name_key' => '岸邊']);

    $ids = collect($this->actingAs($editor)->getJson('/places/suggest?q=岸&tribe=ivalino')->assertOk()->json('places'))->pluck('id')->all();
    expect($ids)->toBe([$local->id, $shared->id])->not->toContain($other->id);
});

it('marks editor places provisional and admin places confirmed', function () {
    $editor = User::factory()->lineEditor()->create();
    $admin = User::factory()->admin()->create();

    $editorId = $this->actingAs($editor)->postJson('/places', ['name' => '待確認', 'tribe' => 'ivalino'])->assertCreated()->json('place.id');
    $adminId = $this->actingAs($admin)->postJson('/places', ['name' => '共用正式', 'tribe' => null])->assertCreated()->json('place.id');
    expect(Place::find($editorId)->is_provisional)->toBeTrue()
        ->and(Place::find($adminId)->is_provisional)->toBeFalse();
});

it('blocks assigning a used shared place to a mismatched tribe', function () {
    $place = Place::factory()->create();
    CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'yayo']);

    expect(fn () => app(PlaceService::class)->update($place, ['name' => $place->name, 'tribe' => 'ivalino']))
        ->toThrow(ValidationException::class);
    expect($place->fresh()->tribe)->toBeNull()->and($place->fresh()->scope_key)->toBe('');
});

it('merges a place atomically including soft deleted records', function () {
    $source = Place::factory()->create(['name' => '描述地名', 'name_key' => '描述地名']);
    $target = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '正式地名', 'name_key' => '正式地名']);
    $session = CaptureSession::factory()->create(['place_id' => $source->id, 'tribe' => 'ivalino']);
    $live = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '描述地名']);
    $deleted = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '描述地名']);
    $deleted->delete();

    app(PlaceService::class)->merge($source, $target);
    expect($session->fresh()->place_id)->toBe($target->id)
        ->and($live->fresh()->location)->toBe('正式地名')
        ->and($deleted->fresh()->location)->toBe('正式地名')
        ->and(Place::find($source->id))->toBeNull();
});

it('rolls back merge failures and rejects cross tribe targets', function () {
    $source = Place::factory()->create();
    $target = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino']);
    $session = CaptureSession::factory()->create(['place_id' => $source->id, 'tribe' => 'yayo']);

    expect(fn () => app(PlaceService::class)->merge($source, $target))->toThrow(ValidationException::class);
    expect($session->fresh()->place_id)->toBe($source->id)->and($source->fresh())->not->toBeNull();
});

it('shows fixed provisional counts filters and confirms a place over HTTP', function () {
    $admin = User::factory()->admin()->create();
    Place::factory()->count(2)->create(['is_provisional' => false]);
    $pending = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'is_provisional' => true]);

    $this->actingAs($admin)->get('/admin/places?provisional=1')->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('provisionalCount', 1)
            ->where('showProvisional', true)
            ->has('places.data', 1)
            ->where('places.data.0.id', $pending->id)
            ->where('places.data.0.capture_sessions_count', 0));

    $this->post("/admin/places/{$pending->id}/confirm")->assertRedirect('/admin/places');
    expect($pending->fresh()->is_provisional)->toBeFalse();
});

it('merges places through the admin endpoint', function () {
    $admin = User::factory()->admin()->create();
    $source = Place::factory()->create();
    $target = Place::factory()->create();
    $session = CaptureSession::factory()->create(['place_id' => $source->id]);

    $this->actingAs($admin)->post("/admin/places/{$source->id}/merge", ['target_place_id' => $target->id])
        ->assertRedirect('/admin/places');
    expect($session->fresh()->place_id)->toBe($target->id);
});
