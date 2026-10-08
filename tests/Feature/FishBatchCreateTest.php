<?php

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders batch create page with session selection props', function () {
    $user = User::factory()->create();
    CaptureSession::factory()->create();

    $this->actingAs($user)->get('/fish/batch-create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BatchCreateFish')
            ->has('selectable_sessions', 1)
            ->has('legacy_combos')
            ->has('upload_limits'));
});

it('redirects unauthenticated users away from batch create page', function () {
    $this->get('/fish/batch-create')->assertRedirect();
});

it('creates a fish and all records from the selected session', function () {
    $user = User::factory()->create();
    $session = CaptureSession::factory()->create([
        'capture_date' => '2026-05-01',
        'tribe' => 'iraraley',
        'capture_method' => 'mamasil',
        'location_hint' => '海邊',
    ]);

    $response = $this->actingAs($user)->post('/fish/batch-create', [
        'name' => 'Batch Fish',
        'filenames' => ['photo1.jpg', 'photo2.jpg', 'photo3.jpg'],
        'session_id' => $session->id,
        'notes' => '測試備註',
    ]);

    $fish = Fish::where('name', 'Batch Fish')->firstOrFail();
    $records = CaptureRecord::where('fish_id', $fish->id)->get();

    expect($records)->toHaveCount(3);
    foreach ($records as $record) {
        expect($record->session_id)->toBe($session->id)
            ->and($record->tribe)->toBe('iraraley')
            ->and($record->location)->toBe('海邊')
            ->and($record->capture_method)->toBe('mamasil')
            ->and($record->capture_date->format('Y-m-d'))->toBe('2026-05-01')
            ->and($record->notes)->toBe('測試備註');
    }

    $response->assertRedirect("/fish/{$fish->id}")->assertSessionHas('success');
});

it('uses the first filename as display image and defaults an empty name', function () {
    $user = User::factory()->create();
    $session = CaptureSession::factory()->create();

    $this->actingAs($user)->post('/fish/batch-create', [
        'name' => '',
        'filenames' => ['first.jpg', 'second.jpg'],
        'session_id' => $session->id,
    ])->assertRedirect();

    $fish = Fish::where('name', '我不知道')->firstOrFail();
    $firstRecord = CaptureRecord::where('fish_id', $fish->id)->where('image_path', 'first.jpg')->firstOrFail();

    expect($fish->display_capture_record_id)->toBe($firstRecord->id);
});

it('requires both filenames and a session source without writing partial data', function (array $payload, array $errors) {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/fish/batch-create', ['name' => 'Invalid Fish', ...$payload])
        ->assertSessionHasErrors($errors);

    expect(Fish::where('name', 'Invalid Fish')->count())->toBe(0)
        ->and(CaptureRecord::count())->toBe(0);
})->with([
    'missing filenames' => [['session_id' => 1], ['filenames']],
    'empty filenames' => [['filenames' => [], 'session_id' => 1], ['filenames']],
    'invalid filename entries' => [['filenames' => [123, null], 'session_id' => 1], ['filenames.0', 'filenames.1']],
    'missing session selection' => [['filenames' => ['photo.jpg']], ['session_id', 'legacy_combo']],
]);

it('rejects client context fields and writes no fish', function () {
    $user = User::factory()->create();
    $session = CaptureSession::factory()->create();

    $this->actingAs($user)->post('/fish/batch-create', [
        'name' => 'Forged Fish',
        'filenames' => ['photo.jpg'],
        'session_id' => $session->id,
        'location' => '偽造地點',
    ])->assertSessionHasErrors('location');

    expect(Fish::where('name', 'Forged Fish')->count())->toBe(0);
});

it('redirects unauthenticated users away from batch create post', function () {
    $this->post('/fish/batch-create', [
        'name' => 'Sneaky Fish',
        'filenames' => ['photo.jpg'],
        'session_id' => 1,
    ])->assertRedirect();

    expect(Fish::count())->toBe(0);
});
