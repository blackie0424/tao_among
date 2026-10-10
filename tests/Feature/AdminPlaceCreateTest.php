<?php

use App\Models\Place;
use App\Models\User;
use App\Services\PlaceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the admin creation form with the six configured tribes', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/places/create')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Places/Create')
        ->has('tribes', 6)->where('tribes', config('fish_options.tribes')));
});

it('blocks non admins from creating places in the admin area', function (string $role) {
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    foreach ([$this->get('/admin/places/create'), $this->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'])] as $response) {
        if ($role === 'guest') {
            $response->assertRedirect('/login');
        } else {
            $response->assertForbidden();
        }
    }
    expect(Place::count())->toBe(0);
})->with(['editor', 'viewer', 'guest']);

it('creates a confirmed normalized place with optional details', function () {
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', [
        'tribe' => 'iraraley', 'name' => '  ZZPLACEMARK  ', 'tao_name' => 'ZZTAOMARK', 'notes' => 'Test note',
    ])->assertRedirect('/admin/places')->assertSessionHas('success', '地名已新增');
    expect(Place::sole()->only(['tribe', 'scope_key', 'name', 'name_key', 'tao_name', 'notes', 'is_provisional']))->toBe([
        'tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => 'ZZPLACEMARK', 'name_key' => 'zzplacemark',
        'tao_name' => 'ZZTAOMARK', 'notes' => 'Test note', 'is_provisional' => false,
    ]);
});

it('rejects missing empty or invalid admin creation tribes', function (array $data, string $message) {
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', ['name' => 'ZZPLACEMARK', ...$data])
        ->assertSessionHasErrors(['tribe' => $message]);
    expect(Place::count())->toBe(0);
})->with([
    'missing' => [[], '請選擇部落'], 'empty' => [['tribe' => ''], '請選擇部落'],
    'invalid' => [['tribe' => 'invalid'], '請選擇有效的部落'],
]);

it('distinguishes confirmed and provisional canonical duplicates', function (bool $provisional, string $message) {
    $place = Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => 'ZZPlace Mark', 'name_key' => 'zzplace mark', 'is_provisional' => $provisional]);
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', ['tribe' => 'iraraley', 'name' => ' zzPLACE　mark '])
        ->assertSessionHasErrors(['name' => $message]);
    expect(Place::count())->toBe(1)->and($place->fresh()->is_provisional)->toBe($provisional);
})->with([
    [false, '此部落已有同名地名'],
    [true, '此部落已有同名的待確認地名，請到待確認清單確認'],
]);

it('allows the same name in another tribe', function () {
    Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => 'ZZPLACEMARK', 'name_key' => 'zzplacemark']);
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'])
        ->assertSessionDoesntHaveErrors()->assertRedirect('/admin/places');
    expect(Place::count())->toBe(2);
});

it('maps a concurrent admin insert collision to the matching validation message', function (bool $provisional, string $message) {
    $service = Mockery::mock(PlaceService::class)->makePartial();
    $service->shouldReceive('create')->once()->with(['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'], false)
        ->andReturnUsing(function () use ($provisional) {
            Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => 'ZZPLACEMARK', 'name_key' => 'zzplacemark', 'is_provisional' => $provisional]);
            throw new QueryException('testing', 'insert into places', [], new PDOException('unique constraint violation'));
        });
    app()->instance(PlaceService::class, $service);
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'])
        ->assertSessionHasErrors(['name' => $message]);
    expect(Place::count())->toBe(1)->and(Place::sole()->is_provisional)->toBe($provisional);
})->with([
    [false, '此部落已有同名地名'], [true, '此部落已有同名的待確認地名，請到待確認清單確認'],
]);

it('does not mask database errors without a matching duplicate', function () {
    $exception = new QueryException('testing', 'insert into places', [], new PDOException('database unavailable'));
    $service = Mockery::mock(PlaceService::class)->makePartial();
    $service->shouldReceive('create')->once()->andThrow($exception);
    app()->instance(PlaceService::class, $service);
    $this->withoutExceptionHandling()->actingAs(User::factory()->admin()->create());
    expect(fn () => $this->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK']))->toThrow($exception);
    expect(Place::count())->toBe(0);
});

it('shows the confirmed new place only in its tribe suggestions', function () {
    $this->actingAs(User::factory()->admin()->create())->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'])->assertSessionDoesntHaveErrors();
    $place = Place::sole();
    $this->actingAs(User::factory()->lineEditor()->create())->getJson('/places/suggest?q=zzplace&tribe=iraraley')
        ->assertOk()->assertJsonCount(1, 'places')->assertJsonPath('places.0.id', $place->id)->assertJsonPath('places.0.is_provisional', false);
    $this->getJson('/places/suggest?q=zzplace&tribe=yayo')->assertOk()->assertJsonCount(0, 'places');
});

it('lists a new place with zero sessions and leaves the provisional count unchanged', function () {
    Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'is_provisional' => true]);
    $this->actingAs(User::factory()->admin()->create())->get('/admin/places')->assertInertia(fn (Assert $page) => $page->where('provisionalCount', 1));
    $this->post('/admin/places', ['tribe' => 'iraraley', 'name' => 'ZZPLACEMARK'])->assertSessionDoesntHaveErrors();
    $this->get('/admin/places')->assertInertia(fn (Assert $page) => $page->has('places.data', 2)->where('provisionalCount', 1)
        ->where('places.data.1.name', 'ZZPLACEMARK')->where('places.data.1.capture_sessions_count', 0)->where('places.data.1.is_provisional', false));
});

it('requires a tribe when updating without changing the existing place', function (array $data) {
    $place = Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => 'Original', 'is_provisional' => true]);
    $before = $place->fresh()->getAttributes();
    $this->actingAs(User::factory()->admin()->create())->put("/admin/places/{$place->id}", ['name' => 'Changed', ...$data])
        ->assertSessionHasErrors(['tribe' => '請選擇部落']);
    expect($place->fresh()->getAttributes())->toBe($before);
})->with(['missing' => [[]], 'null' => [['tribe' => null]]]);

it('rejects full width whitespace names at every place write entry point', function (string $entry) {
    $place = Place::factory()->create(['tribe' => 'iraraley', 'scope_key' => 'iraraley', 'name' => 'Original']);
    $data = ['tribe' => 'iraraley', 'name' => '　　'];
    $this->actingAs(User::factory()->admin()->create());
    if ($entry === 'update') {
        $this->put("/admin/places/{$place->id}", $data)->assertSessionHasErrors(['name' => '請輸入地名']);
    } elseif ($entry === 'json') {
        $this->postJson('/places', $data)->assertUnprocessable()->assertJsonPath('errors.name.0', '請輸入地名');
    } else {
        $this->post('/admin/places', $data)->assertSessionHasErrors(['name' => '請輸入地名']);
    }
    expect(Place::count())->toBe(1)->and($place->fresh()->name)->toBe('Original');
})->with(['update', 'json', 'admin']);
