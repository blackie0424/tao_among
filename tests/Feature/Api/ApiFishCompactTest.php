<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('GET /fish/{id}/compact 存在時回傳 200，含 id、name、image_url', function () {
    $fish = Fish::factory()->create(['name' => '飛魚']);

    $response = $this->getJson("/prefix/api/fish/{$fish->id}/compact");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'image_url'],
        ])
        ->assertJsonPath('data.id', $fish->id)
        ->assertJsonPath('data.name', $fish->name);
});

it('GET /fish/999999/compact 回傳 404', function () {
    $response = $this->getJson('/prefix/api/fish/999999/compact');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'data not found',
            'data' => null,
        ]);
});
