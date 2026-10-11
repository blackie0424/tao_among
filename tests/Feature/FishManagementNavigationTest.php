<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a saved name to the basic tab', function () {
    $user = User::factory()->create();
    Fish::factory()->create(['id' => 114]);
    $this->actingAs($user)->put('/fish/114/name', ['name' => 'Test fish'])
        ->assertRedirect('/fish/114?tab=basic')
        ->assertSessionHas('success', '魚類名稱已更新為「Test fish」！');
});

it('local returns to local tab', function () {
    Fish::factory()->create(['id' => 114]);

    $this->actingAs(User::factory()->create())->post('/fish/114/tribal-classifications', ['classifications' => [['tribe' => 'iraraley', 'notes' => 'Test']]])->assertRedirect('/fish/114?tab=local')->assertSessionHas('success');
});

it('create-note returns to advanced tab', function () {
    Fish::factory()->create(['id' => 114]);

    $this->actingAs(User::factory()->create())->post('/fish/114/knowledge', ['note' => 'Test', 'note_type' => '生態習性', 'locate' => 'iraraley'])->assertRedirect('/fish/114?tab=advanced')->assertSessionHas('success');
});

it('edit-note returns to advanced tab', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishNote::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->put('/fish/114/knowledge/8', ['note' => 'Changed'])->assertRedirect('/fish/114?tab=advanced')->assertSessionHas('success');
});

it('edit-audio returns to audio tab', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishAudio::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->put('/fish/114/audio/8', ['name' => 'Changed'])->assertRedirect('/fish/114?tab=audio')->assertSessionHas('success');
});

it('edit-capture stays at its destination', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\CaptureRecord::factory()->create(['id' => 8, 'fish_id' => 114, 'session_id' => \App\Models\CaptureSession::factory()->create()->id]);
    $this->actingAs(User::factory()->create())->put('/fish/114/capture-records/8', ['notes' => 'Changed'])->assertRedirect('/fish/114?tab=capture')->assertSessionHas('success');
});

it('delete-capture stays at its destination', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\CaptureRecord::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->delete('/fish/114/capture-records/8', [])->assertRedirect('/fish/114/capture-records')->assertSessionHas('success');
});

it('delete-note stays at its destination', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishNote::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->delete('/fish/114/knowledge/8', [])->assertRedirect('/fish/114/knowledge-list')->assertSessionHas('success');
});

it('delete-audio stays at its destination', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishAudio::factory()->create(['id' => 8, 'fish_id' => 114, 'locate' => '']);
    $this->actingAs(User::factory()->create())->delete('/fish/114/audio/8', [])->assertRedirect('/fish/114/audio-list')->assertSessionHas('success');
});

it('primary audio stays on the audio list', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishAudio::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->put('/fish/114/audio/8/set-base')
        ->assertRedirect('/fish/114/audio-list')->assertSessionHas('success');
});

it('returns each batch item as JSON without navigating or flashing whole-batch success', function () {
    Fish::factory()->create(['id' => 114]);
    $session = \App\Models\CaptureSession::factory()->create();
    $this->actingAs(User::factory()->create())->postJson('/fish/114/capture-records', [
        'session_id' => $session->id,
        'image_filename' => 'batch.jpg',
    ])->assertCreated()->assertJson(['success' => true])->assertSessionMissing('success');
});

it('removed fish management URLs return 404', function ($path) {
    Fish::factory()->create(['id' => 114]);
    $this->actingAs(User::factory()->create())->get('/fish/114/'.$path)->assertNotFound();
})->with(['media-manager', 'knowledge-manager', 'tribal-classifications', 'knowledge']);

it('invalid Inertia knowledge creation returns to its form with errors and no success', function () {
    Fish::factory()->create(['id' => 114]);
    $this->actingAs(User::factory()->create())->from('/fish/114/knowledge/create')
        ->withHeader('X-Inertia', 'true')->post('/fish/114/knowledge', [])
        ->assertRedirect('/fish/114/knowledge/create')->assertSessionHasErrors(['note'])
        ->assertSessionMissing('success');
});

it('invalid Inertia knowledge update returns to its form with errors and no success', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishNote::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->from('/fish/114/knowledge/8/edit')
        ->withHeader('X-Inertia', 'true')->put('/fish/114/knowledge/8', ['note' => ''])
        ->assertRedirect('/fish/114/knowledge/8/edit')->assertSessionHasErrors(['note'])
        ->assertSessionMissing('success');
});

it('a failed batch item preserves earlier captures without whole-batch success', function () {
    Fish::factory()->create(['id' => 114]);
    $session = \App\Models\CaptureSession::factory()->create();
    $this->actingAs(User::factory()->create())->postJson('/fish/114/capture-records', [
        'session_id' => $session->id, 'image_filename' => 'saved.jpg', 'notes' => 'saved capture',
    ])->assertCreated()->assertSessionMissing('success');
    $this->postJson('/fish/114/capture-records', ['session_id' => $session->id])
        ->assertUnprocessable()->assertJsonValidationErrors('image_filename')->assertSessionMissing('success');
    $this->get('/fish/114?tab=capture')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Fish')->has('captureRecords', 1)->where('captureRecords.0.notes', 'saved capture'));
});

it('invalid forms return errors without success', function ($method, $url, $form, $data, $field) {
    Fish::factory()->create(['id' => 114]);
    \App\Models\FishAudio::factory()->create(['id' => 8, 'fish_id' => 114]);
    \App\Models\CaptureRecord::factory()->create(['id' => 8, 'fish_id' => 114, 'session_id' => \App\Models\CaptureSession::factory()->create()->id]);
    \App\Models\ReferenceKnowledge::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->from($form)->withHeader('X-Inertia', 'true')
        ->{$method}($url, $data)->assertRedirect($form)->assertSessionHasErrors($field)->assertSessionMissing('success');
})->with([
    ['put', '/fish/114/name', '/fish/114/edit', ['name' => ''], 'name'],
    ['post', '/fish/114/tribal-classifications', '/fish/114/tribal-classifications/create', [], 'classifications'],
    ['put', '/fish/114/capture-records/8', '/fish/114/capture-records/8/edit', ['image_scale' => 5], 'image_scale'],
    ['put', '/fish/114/audio/8', '/fish/114/audio/8/edit', ['name' => ''], 'name'],
    ['post', '/fish/114/reference-knowledge', '/fish/114/reference-knowledge/create', [], 'content'],
    ['put', '/fish/114/reference-knowledge/8', '/fish/114/reference-knowledge/8/edit', ['content' => ''], 'content'],
]);

it('viewer cannot delete or merge fish', function () {
    Fish::factory()->create(['id' => 114]);
    Fish::factory()->create(['id' => 115]);
    $this->actingAs(User::factory()->lineViewer()->create())->delete('/fish/114')->assertForbidden();
    $this->postJson('/prefix/api/fish/merge', ['target_fish_id' => 114, 'source_fish_ids' => [115]])->assertForbidden();
});

it('deleted fish redirects to the list with its name in the success message', function () {
    Fish::factory()->create(['id' => 114, 'name' => 'Test fish']);
    $this->actingAs(User::factory()->create())->delete('/fish/114')
        ->assertRedirect('/fishs')->assertSessionHas('success', '魚類「Test fish」已成功刪除！');
});

it('setting a display capture stays on the capture list', function () {
    Fish::factory()->create(['id' => 114]);
    \App\Models\CaptureRecord::factory()->create(['id' => 8, 'fish_id' => 114]);
    $this->actingAs(User::factory()->create())->from('/fish/114/capture-records')
        ->put('/fish/114/display-image', ['capture_record_id' => 8])
        ->assertRedirect('/fish/114/capture-records')->assertSessionHas('success');
});
