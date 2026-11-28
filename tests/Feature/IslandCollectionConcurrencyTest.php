<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\IslandCollectedFish;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;

uses(DatabaseTruncation::class);

// #232 AC 6：收進圖鑑的檢查與新增，和同時清空記錄的寫入交錯時要一致（工具見 tests/Pest.php）。
// 先開始的程序鎖住那一筆並清空，鎖著等一下才提交；稍後開始的程序收進圖鑑，要等清空提交後才判斷，所以拒收。
const COLLECTION_CLEAR_HOLD_MICROSECONDS = 800000;
const COLLECTION_COLLECT_DELAY_MICROSECONDS = 200000;

afterEach(fn () => deleteConcurrentTestRows());

it('#232 收進圖鑑時另一個程序正在清空自己部落的記錄：等清空提交後拒收，不留收集紀錄', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create();
    $character = IslandCharacter::create(['user_id' => $user->id, 'name' => '阿海', 'tribe' => 'yayo']);
    $fish = Fish::factory()->create();
    $keys = ['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => 'yayo'];
    FishTribeResponse::create([...$keys, 'food_category' => 'oyod']);

    runConcurrentWorkers([
        sprintf(
            'Illuminate\Support\Facades\DB::transaction(function () { '
            .'App\Models\FishTribeResponse::where(%s)->lockForUpdate()->first()->update(["food_category" => null]); '
            .'usleep(%d); });',
            var_export($keys, true), COLLECTION_CLEAR_HOLD_MICROSECONDS
        ),
        sprintf(
            'usleep(%d); App\Models\IslandCharacter::findOrFail(%d)->collect(App\Models\Fish::findOrFail(%d));',
            COLLECTION_COLLECT_DELAY_MICROSECONDS, $character->id, $fish->id
        ),
    ]);

    expect(IslandCollectedFish::count())->toBe(0);
});
