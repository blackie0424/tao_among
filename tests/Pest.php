<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| 多程序並發測試
|--------------------------------------------------------------------------
|
| 啟動多個獨立的 PHP 程序（各自一條資料庫連線）同時執行同一段寫入，驗證 MySQL(InnoDB) 下的交錯。
| 只在 MySQL 上有意義，其他資料庫略過。資料已提交（不在交易內），測試後要自己清掉。
|
*/

const CONCURRENT_WORKER_START_DELAY_SECONDS = 2.0;

function skipUnlessMysqlProcesses(): void
{
    if (Illuminate\Support\Facades\DB::getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
        test()->markTestSkipped('需要 MySQL 與 proc_open');
    }
}

/** 清掉並發測試已提交的回答、魚與帳號（非 MySQL 時沒有寫入，不動） */
function deleteConcurrentTestRows(): void
{
    if (Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
        return;
    }

    Illuminate\Support\Facades\DB::table('fish_tribe_responses')->delete();
    Illuminate\Support\Facades\DB::table('fish')->delete();
    Illuminate\Support\Facades\DB::table('users')->delete();
}

/** 在獨立程序裡、到 $startAt 才執行一段 PHP（已載入 Laravel） */
function startConcurrentWorker(float $startAt, string $statement)
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
function runConcurrentWorkers(array $statements): void
{
    $startAt = microtime(true) + CONCURRENT_WORKER_START_DELAY_SECONDS;
    $workers = array_map(fn (string $statement) => startConcurrentWorker($startAt, $statement), $statements);

    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        expect(proc_close($process))->toBe(0, $output);
    }
}
