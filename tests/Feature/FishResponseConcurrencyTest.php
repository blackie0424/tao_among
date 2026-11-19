<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);

// 這個測試啟動多個獨立的 PHP 程序（各自一條資料庫連線）同時送出同一筆回答，
// 驗證 MySQL(InnoDB) 下「首次新增」的交錯不會死鎖或失敗。只在 MySQL 上有意義，其他資料庫略過。
const CONCURRENT_WORKERS = 4;
const CONCURRENT_ROUNDS = 5;
const WORKER_START_DELAY_SECONDS = 2.0;

afterEach(function () {
    // 本測試的資料已提交（不在交易內），清掉以免影響其他測試
    DB::table('fish_tribe_responses')->delete();
    DB::table('fish')->delete();
    DB::table('users')->delete();
});

function startWorker(float $startAt, int $userId, int $fishId, int $worker)
{
    $code = sprintf(
        'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
        .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); '
        .'while (microtime(true) < %F) { usleep(500); } '
        .'App\Models\FishTribeResponse::submit(["user_id" => %d, "fish_id" => %d, "tribe" => "yayo"], "worker%d", null);',
        $startAt, $userId, $fishId, $worker
    );
    $process = proc_open(
        [PHP_BINARY, '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        array_merge(getenv(), ['APP_ENV' => 'testing'])
    );

    return [$process, $pipes];
}

it('lets simultaneous first submissions for the same key all succeed on MySQL', function () {
    if (DB::getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
        test()->markTestSkipped('需要 MySQL 與 proc_open');
    }

    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < CONCURRENT_ROUNDS; $round++) {
        $fish = Fish::factory()->create();
        $startAt = microtime(true) + WORKER_START_DELAY_SECONDS;
        $workers = [];
        for ($worker = 0; $worker < CONCURRENT_WORKERS; $worker++) {
            $workers[] = startWorker($startAt, $user->id, $fish->id, $worker);
        }

        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            expect(proc_close($process))->toBe(0, $output);
        }

        $rows = FishTribeResponse::where('fish_id', $fish->id)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->name)->toStartWith('worker');
    }
});
