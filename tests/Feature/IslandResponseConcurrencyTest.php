<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;

uses(DatabaseTruncation::class);

// 多個獨立程序同時寫同一筆回答，驗證魚島一題一題送出與 among 魚頁送出交錯時，
// 各自的欄位都保留、不死鎖（工具見 tests/Pest.php）。
const ISLAND_CONCURRENT_ROUNDS = 5;

afterEach(fn () => deleteConcurrentTestRows());

function islandQuestionStatement(array $keys, string $question, string $value): string
{
    return sprintf('App\Models\FishTribeResponse::submitQuestion(%s, %s, %s, "iratay");', var_export($keys, true), var_export($question, true), var_export($value, true));
}

it('新的一筆：魚島兩題同時送出，只有一筆、兩題都保留', function () {
    skipUnlessMysqlProcesses();
    $user = User::factory()->create(['role' => 'viewer']);

    for ($round = 0; $round < ISLAND_CONCURRENT_ROUNDS; $round++) {
        $keys = ['user_id' => $user->id, 'fish_id' => Fish::factory()->create()->id, 'tribe' => 'yayo'];

        runConcurrentWorkers([
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

        runConcurrentWorkers([
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

        runConcurrentWorkers([
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

        runConcurrentWorkers([
            islandQuestionStatement($keys, 'food_category', 'oyod'),
            islandQuestionStatement($keys, 'food_category', 'rahet'),
        ]);

        $rows = FishTribeResponse::where($keys)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->food_category)->toBeIn(['oyod', 'rahet']);
    }
});
