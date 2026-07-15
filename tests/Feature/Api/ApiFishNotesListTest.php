<?php

use App\Models\Fish;
use App\Models\FishNote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('GET /fish/{id}/notes 回傳 200，data 為陣列', function () {
    $fish = Fish::factory()->create();

    $response = $this->getJson("/prefix/api/fish/{$fish->id}/notes");

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data', 'lastUpdateTime']);

    expect($response->json('data'))->toBeArray();
});

it('GET /fish/{id}/notes 含有 note 時，data[0] 含 id、note 欄位', function () {
    $fish = Fish::factory()->create();
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => '這是一筆測試筆記',
        'note_type' => 'observation',
        'locate' => 'yayo',
    ]);

    $response = $this->getJson("/prefix/api/fish/{$fish->id}/notes");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'note'],
            ],
        ]);

    expect($response->json('data.0.note'))->toBe('這是一筆測試筆記');
});

it('GET /fish/999999/notes 回傳 404', function () {
    $response = $this->getJson('/prefix/api/fish/999999/notes');

    $response->assertStatus(404);
});
