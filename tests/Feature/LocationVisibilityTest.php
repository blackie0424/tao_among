<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use App\Services\LocationVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('recursively removes locations for roles without location access', function (string $role) {
    $payload = [
        'location' => 'ZZLOCATIONMARK',
        'nested' => [['location' => 'ZZLOCATIONMARK', 'locate' => '東清部落']],
    ];

    $filtered = app(LocationVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    );

    expect($filtered)->toBe(['nested' => [['locate' => '東清部落']]]);
})->with(['guest', 'viewer']);

it('preserves locations for roles with location access', function (string $role) {
    $payload = ['location' => 'ZZLOCATIONMARK', 'locate' => '東清部落'];

    expect(app(LocationVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    ))->toBe($payload);
})->with(['editor', 'admin']);

it('filters location from API responses for viewers while preserving locate', function () {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'location' => 'ZZLOCATIONMARK',
        'tribe' => 'ivalino',
    ]);

    $response = $this->actingAs(User::factory()->lineViewer()->create())
        ->getJson('/prefix/api/capture-records')
        ->assertOk();

    expect($response->getContent())->not->toContain('ZZLOCATIONMARK')
        ->and($response->json('data.0'))->not->toHaveKey('location')
        ->and($response->json('data.0.tribe'))->toBe('ivalino');
});

it('preserves API location for editors and admins', function (string $role) {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $fish->id, 'location' => 'ZZLOCATIONMARK']);

    $response = $this->actingAs(User::factory()->create(['role' => $role]))
        ->getJson('/prefix/api/capture-records')
        ->assertOk();

    expect($response->json('data.0.location'))->toBe('ZZLOCATIONMARK');
})->with(['editor', 'admin']);

it('filters location from fish detail and capture record pages for viewers', function () {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $fish->id, 'location' => 'ZZLOCATIONMARK']);
    $viewer = User::factory()->lineViewer()->create();

    $this->actingAs($viewer)
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->missing('captureRecords.0.location'));

    $this->get("/fish/{$fish->id}/capture-records")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CaptureRecords')
            ->missing('fish.captureRecords.0.location'));
});

it('preserves location on fish detail and capture record pages for editors and admins', function (string $role) {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $fish->id, 'location' => 'ZZLOCATIONMARK']);
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->where('captureRecords.0.location', 'ZZLOCATIONMARK'));

    $this
        ->get("/fish/{$fish->id}/capture-records")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CaptureRecords')
            ->where('fish.captureRecords.0.location', 'ZZLOCATIONMARK'));
})->with(['editor', 'admin']);

it('hides location search options and ignores location probes for viewers', function () {
    $markedFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $markedFish->id, 'location' => 'ZZLOCATIONMARK']);
    $otherFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $otherFish->id, 'location' => '其他地點']);
    $viewer = User::factory()->lineViewer()->create();

    $plain = $this->actingAs($viewer)->get('/fishs')->assertOk();
    $probed = $this->get('/fishs?capture_location=ZZLOCATIONMARK')->assertOk();

    $plainProps = $plain->viewData('page')['props'];
    $probedProps = $probed->viewData('page')['props'];

    expect($plainProps['searchOptions'])->not->toHaveKey('captureLocations')
        ->and($probedProps['filters'])->not->toHaveKey('capture_location')
        ->and(array_column($probedProps['items'], 'id'))
        ->toBe(array_column($plainProps['items'], 'id'));
});

it('preserves location search options and filtering for editors and admins', function (string $role) {
    $markedFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $markedFish->id, 'location' => 'ZZLOCATIONMARK']);
    $otherFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $otherFish->id, 'location' => '其他地點']);

    $response = $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/fishs?capture_location=ZZLOCATIONMARK')
        ->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['searchOptions']['captureLocations'])->toContain('ZZLOCATIONMARK')
        ->and($props['filters']['capture_location'])->toBe('ZZLOCATIONMARK')
        ->and(array_column($props['items'], 'id'))->toBe([$markedFish->id]);
})->with(['editor', 'admin']);

it('ignores location probes and hides location options on the search page for viewers', function () {
    $markedFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $markedFish->id, 'location' => 'ZZLOCATIONMARK']);
    $otherFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $otherFish->id, 'location' => '其他地點']);
    $viewer = User::factory()->lineViewer()->create();

    $plain = $this->actingAs($viewer)->get('/search')->assertOk();
    $probed = $this->get('/search?capture_location=ZZLOCATIONMARK')->assertOk();

    $plainProps = $plain->viewData('page')['props'];
    $probedProps = $probed->viewData('page')['props'];

    expect(array_column($probedProps['fishs'], 'id'))
        ->toBe(array_column($plainProps['fishs'], 'id'))
        ->and($probedProps['filters'])->not->toHaveKey('capture_location')
        ->and($probedProps['searchOptions'])->not->toHaveKey('captureLocations');
});

it('preserves location filtering and options on the search page for editors and admins', function (string $role) {
    $markedFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $markedFish->id, 'location' => 'ZZLOCATIONMARK']);
    $otherFish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $otherFish->id, 'location' => '其他地點']);

    $response = $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/search?capture_location=ZZLOCATIONMARK')
        ->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['filters']['capture_location'])->toBe('ZZLOCATIONMARK')
        ->and($props['searchOptions']['captureLocations'])->toContain('ZZLOCATIONMARK')
        ->and(array_column($props['fishs'], 'id'))->toBe([$markedFish->id]);
})->with(['editor', 'admin']);
