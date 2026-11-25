<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);

// 啟動多個獨立的 PHP 程序（各自一條資料庫連線）同時寫同一筆回答，
// 驗證魚島一題一題送出與 among 魚頁送出交錯時，各自的欄位都保留、不死鎖。只在 MySQL 上有意義，其他資料庫略過。
const ISLAND_CONCURRENT_ROUNDS = 5;
const ISLAND_WORKER_START_DELAY_SECONDS = 2.0;

afterEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        return;
    }

    // 本測試的資料已提交（不在交易內），清掉以免影響其他測試
    DB::table('fish_tribe_responses')->delete();
    DB::table('fish')->delete();
    DB::table('users')->delete();
});

/** 在獨立程序裡、到 $startAt 才執行一段 PHP（已載入 Laravel） */
function startIslandWorker(float $startAt, string $statement)
{
    $code = sprintf(
        'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
        .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); '
        .'while (microtime(true) < %F) { usleep(500); } %s',
        $startAt, $statement
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

/** 同時跑完全部語句，每個程序都要成功結束 */
function runIslandWorkersTogether(array $statements): void
{
    $startAt = microtime(true) + ISLAND_WORKER_START_DELAY_SECONDS;
    $workers = array_map(fn (string $statement) => startIslandWorker($startAt, $statement), $statements);

    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        expect(proc_close($process))->toBe(0, $output);
    }
}

function islandQuestionStatement(array $keys, string $question, string $value): string
{
    return sprintf('App\Models\FishTribeResponse::submitQuestion(%s, %s, %s, "iratay");', var_export($keys, true), var_export($question, true), var_export($value, true));
}

function skipUnlessMysqlProcesses(): void
{
    if (DB::getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
        test()->markTestSkipped('需要 MySQL 與 proc_open');
    }
}

it('新的一筆：魚島兩題同時送出，只有一筆、兩題都保留', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < ISLAND_CONCURRENT_ROUNDS; $round++) {
        $keys = ['user_id' => $user->id, 'fish_id' => Fish::factory()->create()->id, 'tribe' => 'yayo'];

        runIslandWorkersTogether([
            islandQuestionStatement($keys, 'food_category', 'oyod'),
            islandQuestionStatement($keys, 'processing_method', '剝皮'),
        ]);

        $rows = FishTribeResponse::where($keys)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->food_category)->toBe('oyod')
            ->and($rows->first()->processing_method)->toBe('剝皮');
    }
});

it('已有的一筆：魚島兩題同時送出，兩題都保留、原本的叫法不變', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < ISLAND_CONCURRENT_ROUNDS; $round++) {
        $keys = ['user_id' => $user->id, 'fish_id' => Fish::factory()->create()->id, 'tribe' => 'yayo'];
        FishTribeResponse::create([...$keys, 'name' => 'dalakan']);

        runIslandWorkersTogether([
            islandQuestionStatement($keys, 'food_category', 'oyod'),
            islandQuestionStatement($keys, 'processing_method', '剝皮'),
        ]);

        $row = FishTribeResponse::where($keys)->sole();
        expect($row->name)->toBe('dalakan')
            ->and($row->food_category)->toBe('oyod')
            ->and($row->processing_method)->toBe('剝皮');
    }
});

it('among 魚頁送出與魚島處理方式同時送出：各自的欄位與錄音都保留', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < ISLAND_CONCURRENT_ROUNDS; $round++) {
        $keys = ['user_id' => $user->id, 'fish_id' => Fish::factory()->create()->id, 'tribe' => 'yayo'];
        FishTribeResponse::create([...$keys, 'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'recording_seconds' => 5]);

        runIslandWorkersTogether([
            sprintf('App\Models\FishTribeResponse::submit(%s, "dalakan", "rahet");', var_export($keys, true)),
            islandQuestionStatement($keys, 'processing_method', '剝皮'),
        ]);

        $row = FishTribeResponse::where($keys)->sole();
        expect($row->name)->toBe('dalakan')
            ->and($row->food_category)->toBe('rahet')
            ->and($row->processing_method)->toBe('剝皮')
            ->and($row->recording_filename)->toBe('a.m4a');
    }
});

it('魚島同一題同時送出：只有一筆，值是其中之一', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < ISLAND_CONCURRENT_ROUNDS; $round++) {
        $keys = ['user_id' => $user->id, 'fish_id' => Fish::factory()->create()->id, 'tribe' => 'yayo'];

        runIslandWorkersTogether([
            islandQuestionStatement($keys, 'food_category', 'oyod'),
            islandQuestionStatement($keys, 'food_category', 'rahet'),
        ]);

        $rows = FishTribeResponse::where($keys)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->food_category)->toBeIn(['oyod', 'rahet']);
    }
});
