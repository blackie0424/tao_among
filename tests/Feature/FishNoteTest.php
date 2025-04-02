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


// 測試案例 4：查詢魚類詳細資料包含筆記
it('returns fish details with notes', function () {
    $fish = Fish::factory()->create(['name' => 'cilat','type'=>'rahet','locate'=>'yayo','process' => 'isisan','image'=>'cilat.png' ]);
    FishNote::factory()->create([
        'fish_id' => $fish->id,
        'note' => 'Found in deep water.',
        'note_type' => 'habitat',
    ]);

    $response = $this->getJson("/prefix/api/fish/{$fish->id}");

    $response->assertStatus(200)    
        ->assertJson([
            'message' => 'success',
            'data' => [
                'id' => $fish->id,
                'name' => 'cilat',
                'type'=>'rahet',
                'locate'=>'yayo',
                'process' => 'isisan',
                'image'=>'http://tao_among.test/images/cilat.png',
                'notes' => [
                    [
                        'fish_id' => $fish->id,
                        'note' => 'Found in deep water.',
                        'note_type' => 'habitat',
                    ],
                ]
            ],
        ])
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'name',
                'type',
                'locate',
                'process',
                'image',
                'notes' => [
                    '*' => [
                        'fish_id',
                        'note',
                        'note_type',
                    ],
                ],
            ],
            'lastUpdateTime'
        ]); 
});