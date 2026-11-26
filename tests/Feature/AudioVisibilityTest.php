<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use App\Services\AudioVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function fishWithAudio(): Fish
{
    $fish = Fish::factory()->create(['audio_filename' => 'primary-audio.m4a']);
    FishAudio::factory()->create([
        'fish_id' => $fish->id,
        'name' => 'primary-audio.m4a',
        'duration' => 1800,
    ]);

    return $fish;
}

it('recursively removes audio data for roles without audio access', function (string $role) {
    $payload = [
        'data' => [[
            'id' => 1,
            'audio_url' => 'https://example.test/audio.m4a',
            'audio_duration' => 1800,
            'audio_filename' => 'audio.m4a',
            'audios' => [['name' => 'audio.m4a', 'url' => 'https://example.test/audio.m4a']],
            'nested' => ['audio_url' => 'https://example.test/nested.m4a', 'name' => 'kept'],
        ]],
    ];

    $filtered = app(AudioVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    );

    expect($filtered['data'][0])
        ->not->toHaveKeys(['audio_url', 'audio_duration', 'audio_filename', 'audios'])
        ->and($filtered['data'][0]['nested'])
        ->toBe(['name' => 'kept']);
})->with(['guest', 'viewer']);

it('preserves audio data for roles with audio access', function (string $role) {
    $payload = ['audio_url' => 'https://example.test/audio.m4a', 'audios' => [['url' => 'audio']]];

    $filtered = app(AudioVisibilityService::class)->filter(
        $payload,
        User::factory()->create(['role' => $role])
    );

    expect($filtered)->toBe($payload);
})->with(['editor', 'admin']);

it('omits every audio field from fish API responses for viewers', function () {
    $fish = fishWithAudio();

    $response = $this->actingAs(User::factory()->lineViewer()->create())
        ->getJson("/prefix/api/fish/{$fish->id}")
        ->assertOk();

    $data = $response->json('data');

    expect($data)->not->toHaveKeys(['audio_url', 'audio_duration', 'audio_filename', 'audios']);
});

it('preserves every audio field in fish API responses for editors and admins', function (string $role) {
    $fish = fishWithAudio();

    $response = $this->actingAs(User::factory()->create(['role' => $role]))
        ->getJson("/prefix/api/fish/{$fish->id}")
        ->assertOk();

    expect($response->json('data'))
        ->toHaveKeys(['audio_url', 'audio_duration', 'audio_filename', 'audios']);
})->with(['editor', 'admin']);

it('omits audio data from fish Inertia props for viewers', function () {
    $fish = fishWithAudio();

    $this->actingAs(User::factory()->lineViewer()->create())
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->missing('fish.audio_url')
            ->missing('fish.audio_duration')
            ->missing('fish.audio_filename')
            ->missing('fish.audios'));
});

it('preserves audio data in fish Inertia props for editors', function () {
    $fish = fishWithAudio();

    $this->actingAs(User::factory()->lineEditor()->create())
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->has('fish.audio_url')
            ->has('fish.audio_duration')
            ->has('fish.audio_filename')
            ->has('fish.audios', 1));
});

it('does not send soft-deleted recordings to the fish page, so a spelling with only deleted recordings has none (#176 AC 1)', function () {
    $fish = Fish::factory()->create(['name' => 'Ivey、Ivay', 'audio_filename' => null]);
    // 已刪除的編號比較小：若沒被排除，會變成 Ivey 最早的一段
    $deletedIvey = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'deleted-ivey.m4a', 'spoken_name' => 'Ivey']);
    $activeIvey = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'active-ivey.m4a', 'spoken_name' => 'Ivey']);
    $deletedIvay = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'deleted-ivay.m4a', 'spoken_name' => 'Ivay']);
    $deletedIvey->delete();
    $deletedIvay->delete();

    $this->actingAs(User::factory()->lineEditor()->create())
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->has('fish.audios', 1)
            ->where('fish.audios.0.id', $activeIvey->id)
            ->where('fish.audios.0.spoken_name', 'Ivey'));
});

it('sends no recordings to the fish page when every recording is soft-deleted', function () {
    $fish = Fish::factory()->create(['name' => 'Ivey', 'audio_filename' => null]);
    FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'gone.m4a', 'spoken_name' => 'Ivey'])->delete();

    $this->actingAs(User::factory()->lineEditor()->create())
        ->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fish')
            ->has('fish.audios', 0));
});
