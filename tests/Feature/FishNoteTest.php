<?php

use App\Models\Fish;
use App\Models\FishNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;


uses(RefreshDatabase::class);

it('can add note to existing fish', function () {
    $fish = Fish::factory()->create(['name' => 'Salmon']);
    
    $response = $this->postJson("/prefix/api/fish/{$fish->id}/note", [
        'note' => 'This fish is very colorful.',
        'note_type' => 'observation',
        'locate' => 'yayo'
    ]);

    

    $this->assertDatabaseHas('fish_notes', [
        'fish_id' => $fish->id,
        'note' => 'This fish is very colorful.',
        'note_type' => 'observation',
        'locate' => 'yayo'
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


// 測試案例 4：查詢魚類詳細資料包含筆記，查詢需要的元素有魚類id跟地區locate，預設傳回Iraraley的資料
it('returns fish details with notes, defaulting to Iraraley when no locate parameter is provided', function () {
    $fish = Fish::factory()->create(['id' => 1]);
    // 創建兩筆筆記，屬於同一隻魚，但不同 locate
    $fishNote1 = FishNote::factory()->create([
        'fish_id' => $fish->id,
        'locate' => 'iraraley',
    ]);

    $fishNote2 =FishNote::factory()->create([
        'fish_id' =>  $fish->id,
        'locate' => 'yayo',
    ]);

    $response = $this->getJson("/prefix/api/fish/{$fish->id}");

    $response->assertStatus(200)    
        ->assertJson([
            'message' => 'success',
            'data' => [
                'id' =>1,
                'notes' => [
                    [
                        'fish_id' =>  $fish->id,
                        'locate'=> 'iraraley',
                    ],
                ]
            ],
        ]);
});

it('returns notes since a given time for a fish', function () {
    $fish = Fish::factory()->create(['name' => 'cilat']);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'Old note',
        'note_type' => 'habitat',
        'created_at' => Carbon::parse('2025-03-01'),
        'locate' => 'yayo'
    ]);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'Recent note',
        'note_type' => 'habitat',
        'created_at' => Carbon::parse('2025-04-02'),
        'locate' => 'yayo'
    ]);

    $since = Carbon::parse('2025-04-01')->timestamp;
    $response = $this->getJson("/prefix/api/fish/{$fish->id}/notes?since={$since}");
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => [
                [
                    'fish_id' => $fish->id,
                    'note' => 'Recent note',
                    'note_type' => 'habitat',
                    'locate' => 'yayo'
                ],
            ],
        ])
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['fish_id', 'note', 'note_type','locate']],
            'lastUpdateTime',
        ]);
});

it('returns no notes since a given time for a fish', function () {
    $fish = Fish::factory()->create(['name' => 'cilat']);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'Old note',
        'note_type' => 'habitat',
        'created_at' => Carbon::parse('2025-03-01'),
    ]);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'Old note',
        'note_type' => 'habitat',
        'created_at' => Carbon::parse('2025-03-31'),
    ]);

    $since = Carbon::parse('2025-04-01')->timestamp;
    $response = $this->getJson("/prefix/api/fish/{$fish->id}/notes?since={$since}");
    
    $response->assertStatus(200)
        ->assertExactJson([
            'message' => 'success',
            'data' => [],
            'lastUpdateTime' => $response->json('lastUpdateTime')
        ])
        ->assertJsonStructure([
            'message',
            'data',
            'lastUpdateTime',
        ]);
});

it('returns all notes when since is not provided for a fish', function () {
    $fish = Fish::factory()->create(['name' => 'cilat']);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'note1',
        'note_type' => 'habitat',
    ]);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'note2',
        'note_type' => 'habitat',
    ]);

    $response = $this->getJson("/prefix/api/fish/{$fish->id}/notes");
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => [
                [
                    'fish_id' => $fish->id,
                    'note' => 'note1',
                    'note_type' => 'habitat',
                ],
                [
                    'fish_id' => $fish->id,
                    'note' => 'note2',
                    'note_type' => 'habitat',
                ]
            ],
            'lastUpdateTime' => $response->json('lastUpdateTime')
        ])
        ->assertJsonStructure([
            'message',
            'data',
            'lastUpdateTime',
        ]);
});

it('can update an existing fish note', function () {
    $fish = Fish::factory()->create();
    $note = FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'original note',
        'note_type' => 'observation',
        'locate' => 'yayo'
    ]);

    $response = $this->putJson("/prefix/api/fish/{$fish->id}/note/{$note->id}", [
        'note' => 'updated note',
        'note_type' => 'research',
        'locate' => 'iraraley'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Fish note updated successfully',
            'data' => [
                'id' => $note->id,
                'fish_id' => $fish->id,
                'note' => 'updated note',
                'note_type' => 'research',
                'locate' => 'iraraley',
            ]
        ]);

    $this->assertDatabaseHas('fish_notes', [
        'id' => $note->id,
        'fish_id' => $fish->id,
        'note' => 'updated note',
        'note_type' => 'research',
        'locate' => 'iraraley',
    ]);
});

it('returns 404 when updating a non-existent fish note', function () {
    $fish = Fish::factory()->create();
    $invalidNoteId = 9999;

    $response = $this->putJson("/prefix/api/fish/{$fish->id}/note/{$invalidNoteId}", [
        'note' => 'should not update',
        'note_type' => 'observation',
        'locate' => 'yayo'
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'fish note not found',
            'data' => null,
        ]);
});