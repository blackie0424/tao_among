<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use App\Models\User;
use App\Services\PlaceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    $this->actingAs($admin)->postJson('/places', ['name' => '正式', 'tribe' => null])->assertUnprocessable()->assertJsonValidationErrors('tribe');
    expect(Place::count())->toBe(1);
    $adminId = $this->postJson('/places', ['name' => '正式', 'tribe' => 'ivalino'])->assertCreated()->json('place.id');
    expect(Place::find($editorId)->is_provisional)->toBeTrue()
        ->and(Place::find($adminId)->is_provisional)->toBeFalse();
});

it('ignores request scope keys and confirms only provisional places when saved', function () {
    $admin = User::factory()->admin()->create();
    $pending = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'is_provisional' => true]);
    $confirmed = Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'is_provisional' => false]);

    $this->actingAs($admin)->put("/admin/places/{$pending->id}", ['name' => $pending->name, 'tribe' => 'ivalino', 'scope_key' => 'forged'])->assertRedirect('/admin/places');
    $this->put("/admin/places/{$confirmed->id}", ['name' => $confirmed->name, 'tribe' => 'yayo', 'scope_key' => 'forged'])->assertRedirect('/admin/places');

    expect($pending->fresh()->only(['scope_key', 'is_provisional']))->toBe(['scope_key' => 'ivalino', 'is_provisional' => false])
        ->and($confirmed->fresh()->only(['scope_key', 'is_provisional']))->toBe(['scope_key' => 'yayo', 'is_provisional' => false]);
});

it('validates an existing place when a capture session changes tribe', function () {
    $editor = User::factory()->lineEditor()->create();
    $local = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino']);
    $shared = Place::factory()->create();
    $session = CaptureSession::factory()->create(['tribe' => 'ivalino', 'place_id' => $local->id]);
    $payload = ['capture_date' => '2026-10-01', 'tribe' => 'yayo', 'capture_method' => '釣魚', 'notes' => null];

    $this->actingAs($editor)->put("/capture-sessions/{$session->id}", [...$payload, 'place_id' => $local->id])->assertSessionHasErrors(['place_id' => '這個地名不屬於所選部落']);
    expect($session->fresh()->only(['tribe', 'place_id']))->toBe(['tribe' => 'ivalino', 'place_id' => $local->id]);
    $this->put("/capture-sessions/{$session->id}", [...$payload, 'place_id' => $shared->id])->assertRedirect();
    expect($session->fresh()->only(['tribe', 'place_id']))->toBe(['tribe' => 'yayo', 'place_id' => $shared->id]);
});
it('blocks assigning a used shared place to a mismatched tribe', function () {
    $place = Place::factory()->create();
    CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'yayo']);

    expect(fn () => app(PlaceService::class)->update($place, ['name' => $place->name, 'tribe' => 'ivalino']))
        ->toThrow(ValidationException::class);
    expect($place->fresh()->tribe)->toBeNull()->and($place->fresh()->scope_key)->toBe('');
});

it('lists only merge targets compatible with every source session tribe', function () {
    $admin = User::factory()->admin()->create();
    $source = Place::factory()->create();
    CaptureSession::factory()->create(['place_id' => $source->id, 'tribe' => 'ivalino']);
    $shared = Place::factory()->create(['name' => '共用', 'name_key' => '共用']);
    $sameTribe = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '同部落', 'name_key' => '同部落']);
    $otherTribe = Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => '其他部落', 'name_key' => '其他部落']);

    $targets = $this->actingAs($admin)->get("/admin/places/{$source->id}/edit")->assertOk()
        ->viewData('page')['props']['mergeTargets'];

    expect(collect($targets)->pluck('id')->all())
        ->toContain($shared->id, $sameTribe->id)
        ->not->toContain($otherTribe->id);
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

it('rejects merging a place into itself', function () {
    $admin = User::factory()->admin()->create();
    $place = Place::factory()->create();
    $this->actingAs($admin)->post("/admin/places/{$place->id}/merge", ['target_place_id' => $place->id])->assertSessionHasErrors(['target_place_id' => '不能併入同一個地名']);
    expect($place->fresh())->not->toBeNull();
});

it('rolls back every merge write when record synchronization fails', function () {
    $source = Place::factory()->create(['name' => '來源', 'name_key' => '來源']);
    $target = Place::factory()->create(['name' => '目標', 'name_key' => '目標']);
    $session = CaptureSession::factory()->create(['place_id' => $source->id]);
    CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '來源']);
    DB::listen(function ($query): void {
        $sql = str_replace('`', '"', $query->sql);
        if (str_contains($sql, 'update "capture_records"')) {
            throw new RuntimeException('forced synchronization failure');
        }
    });
    expect(fn () => app(PlaceService::class)->merge($source, $target))->toThrow(RuntimeException::class);
    expect($session->fresh()->place_id)->toBe($source->id)->and($source->fresh())->not->toBeNull()->and(CaptureRecord::first()->location)->toBe('來源');
});

it('locks both places in stable id order before reading source sessions', function () {
    $first = Place::factory()->create();
    $second = Place::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $sql = str_replace('`', '"', $query->sql);
        if (str_contains($sql, 'from "places"') || str_contains($sql, 'from "capture_sessions"')) {
            $queries[] = [$sql, $query->bindings];
        }
    });
    app(PlaceService::class)->merge($second, $first);
    $placeLockIndex = collect($queries)->search(fn ($query) => str_contains($query[0], 'from "places"') && str_contains($query[0], 'order by "id" asc'));
    $sessionReadIndex = collect($queries)->search(fn ($query) => str_contains($query[0], 'from "capture_sessions"'));
    expect($queries[$placeLockIndex][0])->toContain("in ({$first->id}, {$second->id})")->and($placeLockIndex)->toBeLessThan($sessionReadIndex);
});

it('reuses the winning place when concurrent name resolution hits the unique key', function () {
    $inserted = false;
    DB::listen(function ($query) use (&$inserted): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (! $inserted && str_contains($sql, 'select') && str_contains($sql, 'from places') && str_contains($sql, 'name_key')) {
            $inserted = true;
            Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '競態地名', 'name_key' => '競態地名', 'is_provisional' => true]);
        }
    });

    $resolved = app(PlaceService::class)->resolveName('競態地名', 'ivalino');
    expect($resolved->name)->toBe('競態地名')
        ->and(Place::where('scope_key', 'ivalino')->where('name_key', '競態地名')->count())->toBe(1);
});

it('treats a blank typed place name as no place', function () {
    $editor = User::factory()->lineEditor()->create();
    $payload = ['capture_date' => '2026-10-01', 'tribe' => 'ivalino', 'capture_method' => '釣魚', 'notes' => null];

    $response = $this->actingAs($editor)->postJson('/capture-sessions', [...$payload, 'place_name' => "  \u{3000}  "])->assertCreated();
    expect($response->json('session.place_id'))->toBeNull()
        ->and(Place::count())->toBe(0);
});

it('releases the old scope when changing tribe and rejects an occupied new scope', function () {
    $place = Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '港口', 'name_key' => '港口']);
    app(PlaceService::class)->update($place, ['name' => '港口', 'tribe' => 'yayo']);
    $replacement = app(PlaceService::class)->create(['name' => '港口', 'tribe' => 'ivalino'], false);
    $occupied = Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => '港口', 'name_key' => '港口']);

    expect(fn () => app(PlaceService::class)->update($place->fresh(), ['name' => '港口', 'tribe' => 'iraraley']))->toThrow(ValidationException::class)
        ->and($replacement->scope_key)->toBe('ivalino')
        ->and($place->fresh()->scope_key)->toBe('yayo')
        ->and($occupied->fresh()->scope_key)->toBe('iraraley');
});

it('allows a shared place used only by one tribe to move there and back to shared', function () {
    $place = Place::factory()->create(['name' => '礁岩', 'name_key' => '礁岩']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'ivalino']);
    $record = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '礁岩']);
    $record->delete();

    app(PlaceService::class)->update($place, ['name' => '礁岩', 'tribe' => 'ivalino']);
    expect($place->fresh()->only(['tribe', 'scope_key']))->toBe(['tribe' => 'ivalino', 'scope_key' => 'ivalino']);
    app(PlaceService::class)->update($place->fresh(), ['name' => '礁岩', 'tribe' => null]);
    expect($place->fresh()->only(['tribe', 'scope_key']))->toBe(['tribe' => null, 'scope_key' => ''])
        ->and($record->fresh()->location)->toBe('礁岩');
});

it('uses stable ascending locks for the forward merge direction too', function () {
    $first = Place::factory()->create();
    $second = Place::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $sql = str_replace('`', '"', $query->sql);
        if (str_contains($sql, 'from "places"') || str_contains($sql, 'from "capture_sessions"')) {
            $queries[] = $sql;
        }
    });

    app(PlaceService::class)->merge($first, $second);
    $placeLockIndex = collect($queries)->search(fn ($sql) => str_contains($sql, 'from "places"') && str_contains($sql, 'order by "id" asc'));
    $sessionReadIndex = collect($queries)->search(fn ($sql) => str_contains($sql, 'from "capture_sessions"'));
    expect($queries[$placeLockIndex])->toContain("in ({$first->id}, {$second->id})")
        ->and($placeLockIndex)->toBeLessThan($sessionReadIndex);
});

it('rejects assigning a shared place when another tribe session only has a deleted record', function () {
    $place = Place::factory()->create(['name' => '共用礁岩', 'name_key' => '共用礁岩']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id, 'tribe' => 'yayo']);
    $record = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '共用礁岩']);
    $record->delete();

    expect(fn () => app(PlaceService::class)->update($place, ['name' => '共用礁岩', 'tribe' => 'ivalino']))
        ->toThrow(ValidationException::class, '此地名被其他部落的情境使用');
    expect($place->fresh()->only(['tribe', 'scope_key']))->toBe(['tribe' => null, 'scope_key' => ''])
        ->and($record->fresh()->location)->toBe('共用礁岩');
});
