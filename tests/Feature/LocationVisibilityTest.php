<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use App\Services\LocationVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recursively removes locations for roles without location access', function (string $role) {
    $payload = [
        'location' => 'ZZ地名標記',
        'nested' => [['location' => 'ZZ地名標記', 'locate' => '東清部落']],
    ];

    $filtered = app(LocationVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    );

    expect($filtered)->toBe(['nested' => [['locate' => '東清部落']]]);
})->with(['guest', 'viewer']);

it('preserves locations for roles with location access', function (string $role) {
    $payload = ['location' => 'ZZ地名標記', 'locate' => '東清部落'];

    expect(app(LocationVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    ))->toBe($payload);
})->with(['editor', 'admin']);

it('filters location from API responses for viewers while preserving locate', function () {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create([
        'fish_id' => $fish->id,
        'location' => 'ZZ地名標記',
        'tribe' => config('fish_options.tribes')[0],
    ]);

    $response = $this->actingAs(User::factory()->lineViewer()->create())
        ->getJson('/prefix/api/capture-records')
        ->assertOk();

    expect($response->getContent())->not->toContain('ZZ地名標記')
        ->and($response->json('data.0'))->not->toHaveKey('location')
        ->and($response->json('data.0.tribe'))->toBe(config('fish_options.tribes')[0]);
});

it('preserves API location for editors and admins', function (string $role) {
    $fish = Fish::factory()->create();
    CaptureRecord::factory()->create(['fish_id' => $fish->id, 'location' => 'ZZ地名標記']);

    $response = $this->actingAs(User::factory()->create(['role' => $role]))
        ->getJson('/prefix/api/capture-records')
        ->assertOk();

    expect($response->json('data.0.location'))->toBe('ZZ地名標記');
})->with(['editor', 'admin']);