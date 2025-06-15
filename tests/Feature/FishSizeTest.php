<?php

use App\Models\Fish;
use App\Models\FishSize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can get fish size by fish_id', function () {
    // 建立一個 fish
    $fish = Fish::factory()->create();

    // 建立 fish_size 資料
    $parts = ["手指1", "手指2", "半掌1", "半掌2", "手掌", "下臂1"];
    $fishSize = FishSize::create([
        'fish_id' => $fish->id,
        'parts' => $parts,
    ]);

    // 呼叫 API
    $response = $this->getJson("/prefix/api/fishSize/{$fish->id}");

    $response->assertStatus(200)
        ->assertJson([
            'fish_id' => $fish->id,
            'parts' => $parts,
        ]);
});
