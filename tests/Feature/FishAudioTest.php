<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\FishAudio;

uses(RefreshDatabase::class);

it('可以建立 fishAudio 資料並正確存取', function () {
    // 建立一個 fish 物件
    $fish = Fish::factory()->create();

    // 建立一個 fishAudio 物件
    $audio = FishAudio::create([
        'fish_id' => $fish->id,
        'name' => 'https://example.com/audio/test.webm',
        'locate' => 'yayo',
    ]);

    // 驗證資料庫有這筆 fishAudio 資訊
    $this->assertDatabaseHas('fish_audios', [
        'id' => $audio->id,
        'fish_id' => $fish->id,
        'name' => 'https://example.com/audio/test.webm',
        'locate' => 'yayo',
    ]);
});
