<?php

use App\Models\Fish;
use App\Models\FishNote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can add note to existing fish', function () {
    $fish = Fish::factory()->create(['name' => 'Salmon']);
    
    $response = $this->postJson("/prefix/api/fish/{$fish->id}/note", [
        'note' => 'This fish is very colorful.',
        'note_type' => 'observation',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Note added successfully',
            'data' => [
                'fish_id' => $fish->id,
                'note' => 'This fish is very colorful.',
                'note_type' => 'observation',
            ],
        ]);

    $this->assertDatabaseHas('fish_notes', [
        'fish_id' => $fish->id,
        'note' => 'This fish is very colorful.',
        'note_type' => 'observation',
    ]);
});



it('returns 404 when querying non-existent fish id', function () {
    $response = $this->getJson('/prefix/api/fish/999');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'data not found',
            'data' => null,
        ]);
});

it('fails to add note with missing required fields', function () {
    $fish = Fish::factory()->create();
    
    $response = $this->postJson("/prefix/api/fish/{$fish->id}/note", [
        'note_type' => 'observation', // 缺少 note
    ]);

    $response->assertStatus(422) // 驗證失敗返回 422
        ->assertJsonValidationErrors(['note']);
});
