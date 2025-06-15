<?php

use App\Models\Fish;
use App\Models\FishSize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can get fish size by fish_id', function () {
    $fish = Fish::factory()->create();
    $parts = ["手指1", "手指2", "半掌1", "半掌2", "手掌", "下臂1"];
    FishSize::create([
        'fish_id' => $fish->id,
        'parts' => $parts,
    ]);

    $response = $this->getJson("/prefix/api/fishSize/{$fish->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => '取得成功',
            'data' => [
                'fish_id' => $fish->id,
                'parts' => $parts,
            ],
        ]);
});

it('returns 404 when fish_id does not exist', function () {
    $response = $this->getJson('/prefix/api/fishSize/999999');
    $response->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Not Found',
            'data' => null,
        ]);
});

it('returns 404 when fish_id is not a number', function () {
    $response = $this->getJson('/prefix/api/fishSize/abc');
    $response->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Not Found',
            'data' => null,
        ]);
});

it('returns 200 and empty parts array when fish_size exists but parts is empty', function () {
    $fish = Fish::factory()->create();
    FishSize::create([
        'fish_id' => $fish->id,
        'parts' => [],
    ]);

    $response = $this->getJson("/prefix/api/fishSize/{$fish->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => '取得成功',
            'data' => [
                'fish_id' => $fish->id,
                'parts' => [],
            ],
        ]);
});

it('can create a new fish size', function () {
    $fish = \App\Models\Fish::factory()->create();
    $payload = [
        'fish_id' => $fish->id,
        'parts' => ["手指1", "手指2", "半掌1"],
    ];

    $response = $this->postJson('/prefix/api/fishSize', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => '建立成功',
            'data' => [
                'fish_id' => $fish->id,
                'parts' => $payload['parts'],
            ],
        ]);

    $this->assertDatabaseHas('fish_size', [
        'fish_id' => $fish->id,
        'parts' => json_encode($payload['parts']),
    ]);
});

it('returns 422 when creating fish size without fish_id', function () {
    $payload = [
        // 'fish_id' => 缺少
        'parts' => ["手指1", "手指2"],
    ];

    $response = $this->postJson('/prefix/api/fishSize', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['fish_id']);
});
