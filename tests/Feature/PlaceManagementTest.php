<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use App\Models\User;
use App\Services\PlaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('normalizes place names with fixed canonical expectations', function (string $input, string $name, string $key) {
    expect(app(PlaceService::class)->normalize($input))->toBe(['name' => $name, 'name_key' => $key]);
})->with([
    ['  A  B ', 'A B', 'a b'],
    ["A\u{3000}B", 'A B', 'a b'],
    ['Ａ', 'Ａ', 'ａ'],
    ['A', 'A', 'a'],
]);

it('returns fixed Chinese validation messages for invalid places', function () {
    $editor = User::factory()->lineEditor()->create();

    $this->actingAs($editor)->post('/places', [
        'name' => '',
        'tao_name' => str_repeat('a', 256),
    ])->assertSessionHasErrors([
        'name' => '請輸入地名',
        'tao_name' => '族語名稱不可超過 255 個字元',
    ]);
});

it('returns the existing place for canonical duplicates', function () {
    $editor = User::factory()->lineEditor()->create();
    $existing = Place::factory()->create(['name' => 'A B', 'name_key' => 'a b']);

    $this->actingAs($editor)->postJson('/places', ['name' => '  a　b  '])
        ->assertStatus(422)->assertJsonPath('existing_place.id', $existing->id)->assertJsonPath('existing_place.name', 'A B');
});

it('maps a unique-key race to the existing-place 422 response', function () {
    $editor = User::factory()->lineEditor()->create();
    $inserted = false;
    DB::listen(function ($query) use (&$inserted): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (! $inserted && str_contains($sql, 'select') && str_contains($sql, 'from places') && str_contains($sql, 'name_key')) {
            $inserted = true;
            Place::factory()->create(['name' => 'Race Bay', 'name_key' => 'race bay']);
        }
    });

    $this->actingAs($editor)->postJson('/places', ['name' => 'Race Bay'])
        ->assertStatus(422)
        ->assertJsonPath('existing_place.name', 'Race Bay');
    expect(Place::where('name_key', 'race bay')->count())->toBe(1);
});

it('updates linked live and deleted records when an admin renames a place', function () {
    $admin = User::factory()->admin()->create();
    $place = Place::factory()->create(['name' => '舊地名', 'name_key' => '舊地名']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id]);
    $live = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '舊地名']);
    $deleted = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '舊地名']);
    $deleted->delete();

    $this->actingAs($admin)->put("/admin/places/{$place->id}", ['name' => '新地名'])->assertRedirect('/admin/places');
    expect($live->fresh()->location)->toBe('新地名')->and($deleted->fresh()->location)->toBe('新地名');
});

it('allows case-only renames and rejects canonical duplicates', function () {
    $admin = User::factory()->admin()->create();
    $place = Place::factory()->create(['name' => 'East Bay', 'name_key' => 'east bay']);
    Place::factory()->create(['name' => 'Other Bay', 'name_key' => 'other bay']);

    $this->actingAs($admin)->put("/admin/places/{$place->id}", ['name' => 'EAST BAY'])
        ->assertRedirect('/admin/places');
    expect($place->fresh()->only(['name', 'name_key']))
        ->toBe(['name' => 'EAST BAY', 'name_key' => 'east bay']);

    $this->put("/admin/places/{$place->id}", ['name' => ' other　bay '])
        ->assertSessionHasErrors('name');
    expect($place->fresh()->name)->toBe('EAST BAY');
});

it('rolls back a place rename when linked-record synchronization fails', function () {
    $place = Place::factory()->create(['name' => '舊地名', 'name_key' => '舊地名']);
    $session = CaptureSession::factory()->create(['place_id' => $place->id]);
    $record = CaptureRecord::factory()->create(['session_id' => $session->id, 'location' => '舊地名']);

    \Illuminate\Support\Facades\DB::listen(function ($query): void {
        $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
        if (str_starts_with($sql, 'update capture_records')) {
            throw new RuntimeException('forced place synchronization failure');
        }
    });

    expect(fn () => app(PlaceService::class)->update($place, ['name' => '新地名']))
        ->toThrow(RuntimeException::class, 'forced place synchronization failure');
    expect($place->fresh()->name)->toBe('舊地名')
        ->and($record->fresh()->location)->toBe('舊地名');
});

it('blocks editors from admin place management and blocks referenced deletion', function () {
    $place = Place::factory()->create();
    CaptureSession::factory()->create(['place_id' => $place->id]);
    $this->actingAs(User::factory()->lineEditor()->create())->get('/admin/places')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->delete("/admin/places/{$place->id}")->assertSessionHasErrors('place');
});

it('allows an admin to delete an unused place', function () {
    $admin = User::factory()->admin()->create();
    $place = Place::factory()->create();

    $this->actingAs($admin)->delete("/admin/places/{$place->id}")
        ->assertRedirect('/admin/places');
    $this->assertDatabaseMissing('places', ['id' => $place->id]);
});

it('enforces canonical uniqueness in the database', function () {
    Place::factory()->create(['name' => 'A B', 'name_key' => 'a b']);
    expect(fn () => Place::factory()->create(['name' => 'Other', 'name_key' => 'a b']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('suggests at most ten matching existing places', function () {
    $editor = User::factory()->lineEditor()->create();
    Place::factory()->count(12)->sequence(fn ($sequence) => ['name' => "東清 {$sequence->index}", 'name_key' => "東清 {$sequence->index}"])->create();
    $response = $this->actingAs($editor)->getJson('/places/suggest?q=東清')->assertOk();
    expect($response->json('places'))->toHaveCount(10);
});

it('blocks editors from updating and deleting places through admin routes', function () {
    $editor = User::factory()->lineEditor()->create();
    $place = Place::factory()->create();

    $this->actingAs($editor)->put("/admin/places/{$place->id}", ['name' => '新名稱'])
        ->assertForbidden();
    $this->delete("/admin/places/{$place->id}")->assertForbidden();
});

it('filters place suggestions by normalized name or Tao name', function () {
    $editor = User::factory()->lineEditor()->create();
    $nameMatch = Place::factory()->create(['name' => '東清灣', 'name_key' => '東清灣', 'tao_name' => null]);
    $taoMatch = Place::factory()->create(['name' => '朗島灣', 'name_key' => '朗島灣', 'tao_name' => 'Iraraley Coast']);
    $unrelated = Place::factory()->create(['name' => '紅頭灣', 'name_key' => '紅頭灣', 'tao_name' => 'Imorod']);

    $nameIds = collect($this->actingAs($editor)->getJson('/places/suggest?q=東清')->assertOk()->json('places'))->pluck('id');
    expect($nameIds->all())->toBe([$nameMatch->id])
        ->and($nameIds)->not->toContain($unrelated->id);

    $taoIds = collect($this->getJson('/places/suggest?q=Iraraley')->assertOk()->json('places'))->pluck('id');
    expect($taoIds->all())->toBe([$taoMatch->id])
        ->and($taoIds)->not->toContain($unrelated->id);
});

it('renders the admin place index with empty and populated data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/places')->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Places/Index')
            ->has('places.data', 0));

    $place = Place::factory()->create(['name' => '東清灣', 'name_key' => '東清灣']);
    CaptureSession::factory()->create(['place_id' => $place->id]);

    $this->get('/admin/places')->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('places.data', 1)
            ->where('places.data.0.id', $place->id)
            ->where('places.data.0.capture_sessions_count', 1));
});
