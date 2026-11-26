<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;

uses(DatabaseTruncation::class);

// 多個獨立程序同時送出同一筆回答，驗證 MySQL(InnoDB) 下「首次新增」的交錯不會死鎖或失敗（工具見 tests/Pest.php）。
const CONCURRENT_WORKERS = 4;
const CONCURRENT_ROUNDS = 5;

afterEach(fn () => deleteConcurrentTestRows());

it('lets simultaneous first submissions for the same key all succeed on MySQL', function () {
    skipUnlessMysqlProcesses();

    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < CONCURRENT_ROUNDS; $round++) {
        $fish = Fish::factory()->create();
        $keys = var_export(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => 'yayo'], true);
        runConcurrentWorkers(array_map(
            fn (int $worker) => "App\\Models\\FishTribeResponse::submit({$keys}, \"worker{$worker}\", null);",
            range(0, CONCURRENT_WORKERS - 1)
        ));

        $rows = FishTribeResponse::where('fish_id', $fish->id)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->name)->toStartWith('worker');
    }
});
