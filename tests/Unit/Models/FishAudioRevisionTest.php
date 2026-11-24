<?php

use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('新紀錄的 revision 為 0，每次更新（save、軟刪除、還原、整批 update）都加 1', function () {
    $audio = FishAudio::factory()->create();
    expect($audio->fresh()->revision)->toBe(0);

    $audio->update(['duration' => 3000]);
    expect($audio->fresh()->revision)->toBe(1);

    $audio->delete();
    expect(FishAudio::withTrashed()->find($audio->id)->revision)->toBe(2);

    FishAudio::withTrashed()->find($audio->id)->restore();
    expect($audio->fresh()->revision)->toBe(3);

    FishAudio::where('fish_id', $audio->fish_id)->update(['fish_id' => $audio->fish_id]);
    expect($audio->fresh()->revision)->toBe(4);
});

it('revision 不出現在一般序列化', function () {
    expect(FishAudio::factory()->create()->toArray())->not->toHaveKey('revision');
});
