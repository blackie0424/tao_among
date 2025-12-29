<?php

use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tribal Classification API', function () {
    
    it('can get tribal classifications for a fish', function () {
        $fish = Fish::factory()->create();
        
        // Create classifications with specific tribes to avoid unique constraint issues
        $classification1 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley'
        ]);
        
        $classification2 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'imowrod'
        ]);
        
        $classification3 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'ivalino'
        ]);

        $response = $this->getJson("/prefix/api/fish/{$fish->id}/tribal-classifications");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tribal classifications retrieved successfully',
                'fish' => [
                    'id' => $fish->id,
                    'name' => $fish->name
                ]
            ])
            ->assertJsonCount(3, 'data');
    });

    it('returns 404 when fish not found for getting classifications', function () {
        $response = $this->getJson('/prefix/api/fish/99999/tribal-classifications');

        $response->assertStatus(404);
    });

    it('can create a tribal classification', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗',
            'notes' => 'Test classification notes'
        ];

        $response = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Tribal classification created successfully',
                'data' => [
                    'fish_id' => $fish->id,
                    'tribe' => 'iraraley',
                    'food_category' => 'oyod',
                    'processing_method' => '去魚鱗',
                    'notes' => 'Test classification notes'
                ]
            ]);

        $this->assertDatabaseHas('tribal_classifications', [
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗',
            'notes' => 'Test classification notes'
        ]);
    });

    it('can create tribal classification with empty optional fields', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'tribe' => 'iraraley',
            'food_category' => '',
            'processing_method' => '',
            'notes' => null
        ];

        $response = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Tribal classification created successfully',
                'data' => [
                    'fish_id' => $fish->id,
                    'tribe' => 'iraraley',
                    'food_category' => '',
                    'processing_method' => '',
                    'notes' => null
                ]
            ]);
    });

    it('validates required fields when creating classification', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗'
        ];

        $response = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tribe']);
    });

    it('validates tribe field values when creating classification', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'tribe' => 'invalid_tribe',
            'food_category' => 'oyod'
        ];

        $response = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tribe']);
    });

    it('can show a specific tribal classification', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'food_category' => 'oyod'
        ]);

        $response = $this->getJson("/prefix/api/tribal-classifications/{$classification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tribal classification retrieved successfully',
                'data' => [
                    'id' => $classification->id,
                    'fish_id' => $fish->id,
                    'tribe' => 'iraraley',
                    'food_category' => 'oyod'
                ]
            ]);
    });

    it('returns 404 when showing non-existent classification', function () {
        $response = $this->getJson('/prefix/api/tribal-classifications/99999');

        $response->assertStatus(404);
    });

    it('can update a tribal classification', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'food_category' => 'oyod'
        ]);

        $updateData = [
            'tribe' => 'imowrod',
            'food_category' => 'rahet',
            'processing_method' => '不去魚鱗',
            'notes' => 'Updated notes'
        ];

        $response = $this->putJson("/prefix/api/tribal-classifications/{$classification->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tribal classification updated successfully',
                'data' => [
                    'id' => $classification->id,
                    'tribe' => 'imowrod',
                    'food_category' => 'rahet',
                    'processing_method' => '不去魚鱗',
                    'notes' => 'Updated notes'
                ]
            ]);

        $this->assertDatabaseHas('tribal_classifications', [
            'id' => $classification->id,
            'tribe' => 'imowrod',
            'food_category' => 'rahet',
            'processing_method' => '不去魚鱗',
            'notes' => 'Updated notes'
        ]);
    });

    it('validates fields when updating classification', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create([
            'fish_id' => $fish->id
        ]);

        $updateData = [
            'tribe' => 'invalid_tribe',
            'food_category' => 'invalid_category'
        ];

        $response = $this->putJson("/prefix/api/tribal-classifications/{$classification->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tribe', 'food_category']);
    });

    it('can delete a tribal classification', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create([
            'fish_id' => $fish->id
        ]);

        $response = $this->deleteJson("/prefix/api/tribal-classifications/{$classification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tribal classification deleted successfully'
            ]);

        $this->assertSoftDeleted('tribal_classifications', [
            'id' => $classification->id
        ]);
    });

    it('returns 404 when deleting non-existent classification', function () {
        $response = $this->deleteJson('/prefix/api/tribal-classifications/99999');

        $response->assertStatus(404);
    });

    it('orders classifications by tribe and creation date', function () {
        $fish = Fish::factory()->create();
        
        // Create classifications for different tribes
        $classification1 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'yayo',
            'created_at' => now()->subHours(2)
        ]);
        
        $classification2 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'created_at' => now()->subHour()
        ]);
        
        $classification3 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'imowrod',
            'created_at' => now()
        ]);

        $response = $this->getJson("/prefix/api/fish/{$fish->id}/tribal-classifications");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should be ordered by tribe first, then by created_at desc
        expect($data)->toHaveCount(3);
        
        // Check that tribes are ordered alphabetically
        $tribes = collect($data)->pluck('tribe')->toArray();
        expect($tribes)->toBe(['imowrod', 'iraraley', 'yayo']);
    });

    it('prevents duplicate classifications for same fish and tribe', function () {
        $fish = Fish::factory()->create();
        
        $data1 = [
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'notes' => 'First classification'
        ];
        
        $data2 = [
            'tribe' => 'iraraley',
            'food_category' => 'rahet',
            'notes' => 'Second classification'
        ];

        $response1 = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data1);
        $response2 = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data2);

        $response1->assertStatus(201);
        $response2->assertStatus(422); // Should return validation error
        
        $response2->assertJson([
            'message' => 'Validation failed',
            'errors' => [
                'tribe' => ['此魚類已有該部落的地方知識記錄，請直接編輯現有記錄或選擇其他部落。']
            ]
        ]);

        $this->assertDatabaseCount('tribal_classifications', 1);
    });

    it('can restore soft deleted classification when creating with same tribe', function () {
        $fish = Fish::factory()->create();
        
        // 建立第一筆記錄
        $data1 = [
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'notes' => 'First classification'
        ];
        
        $response1 = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data1);
        $response1->assertStatus(201);
        
        $classificationId = $response1->json('data.id');
        
        // 刪除記錄（軟刪除）
        $deleteResponse = $this->deleteJson("/prefix/api/tribal-classifications/{$classificationId}");
        $deleteResponse->assertStatus(200);
        
        // 驗證記錄被軟刪除
        $this->assertSoftDeleted('tribal_classifications', ['id' => $classificationId]);
        
        // 使用相同部落建立新記錄（應該恢復軟刪除的記錄）
        $data2 = [
            'tribe' => 'iraraley',
            'food_category' => 'rahet',
            'notes' => 'Second classification - should restore'
        ];
        
        $response2 = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", $data2);
        $response2->assertStatus(201);
        
        // 驗證記錄被恢復且資料已更新
        $this->assertDatabaseHas('tribal_classifications', [
            'id' => $classificationId,
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'food_category' => 'rahet',
            'notes' => 'Second classification - should restore',
            'deleted_at' => null
        ]);
        
        // 驗證只有一筆記錄（恢復的，不是新建的）
        $this->assertDatabaseCount('tribal_classifications', 1);
        expect($response2->json('data.id'))->toBe($classificationId);
    });
});
