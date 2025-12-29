<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MigrateLegacyFishCommand', function () {
    
    it('shows success message when all fish already have capture records', function () {
        // 建立有捕獲紀錄的魚類
        $fish = Fish::factory()->create();
        CaptureRecord::factory()->create(['fish_id' => $fish->id]);

        $this->artisan('migrate:legacy-fish')
            ->expectsOutput('✅ 所有魚類都已有捕獲紀錄！無需處理。')
            ->assertExitCode(0);
    });

    it('can preview migration with dry-run option', function () {
        // 建立沒有捕獲紀錄的魚類
        $fish = Fish::factory()->create(['name' => '測試魚']);

        $this->artisan('migrate:legacy-fish', ['--dry-run' => true])
            ->expectsOutput('🔍 DRY-RUN 模式（不會真正寫入資料庫）')
            ->expectsOutputToContain('找到 1 筆沒有捕獲紀錄的魚類')
            ->expectsOutputToContain('測試魚')
            ->expectsOutput('⚠️  這只是預覽，尚未寫入資料庫')
            ->assertExitCode(0);

        // 驗證沒有實際建立捕獲紀錄
        expect(CaptureRecord::count())->toBe(0);
    });

    it('creates capture records for fish without records', function () {
        // 建立沒有捕獲紀錄的魚類
        $fish1 = Fish::factory()->create([
            'name' => '魚類A',
            'image' => 'test-image-1.jpg',
            'created_at' => now()->subDays(10)
        ]);

        $fish2 = Fish::factory()->create([
            'name' => '魚類B',
            'image' => 'test-image-2.jpg',
            'created_at' => now()->subDays(5)
        ]);

        // 執行遷移
        $this->artisan('migrate:legacy-fish')
            ->expectsOutputToContain('找到 2 筆沒有捕獲紀錄的魚類')
            ->expectsOutput('✅ 成功建立 2 筆捕獲紀錄')
            ->assertExitCode(0);

        // 驗證捕獲紀錄已建立
        expect(CaptureRecord::count())->toBe(2);

        // 驗證第一筆捕獲紀錄
        $record1 = CaptureRecord::where('fish_id', $fish1->id)->first();
        expect($record1)->not->toBeNull();
        expect($record1->image_path)->toBe('test-image-1.jpg');
        expect($record1->tribe)->toBe('iraraley');
        expect($record1->location)->toBe('不確定');
        expect($record1->capture_method)->toBe('mamasil');
        expect($record1->capture_date->format('Y-m-d'))->toBe($fish1->created_at->format('Y-m-d'));
        expect($record1->notes)->toBe('此為系統新增舊資料');

        // 驗證第二筆捕獲紀錄
        $record2 = CaptureRecord::where('fish_id', $fish2->id)->first();
        expect($record2)->not->toBeNull();
        expect($record2->image_path)->toBe('test-image-2.jpg');
    });

    it('does not affect existing capture records', function () {
        // 建立已有捕獲紀錄的魚類
        $fishWithRecord = Fish::factory()->create();
        $existingRecord = CaptureRecord::factory()->create([
            'fish_id' => $fishWithRecord->id,
            'tribe' => 'ivalino',
            'location' => '原有地點',
        ]);

        // 建立沒有捕獲紀錄的魚類
        $fishWithoutRecord = Fish::factory()->create();

        // 執行遷移
        $this->artisan('migrate:legacy-fish')
            ->assertExitCode(0);

        // 驗證原有的捕獲紀錄未被修改
        $existingRecord->refresh();
        expect($existingRecord->tribe)->toBe('ivalino');
        expect($existingRecord->location)->toBe('原有地點');

        // 驗證新建立的捕獲紀錄
        $newRecord = CaptureRecord::where('fish_id', $fishWithoutRecord->id)->first();
        expect($newRecord)->not->toBeNull();
        expect($newRecord->tribe)->toBe('iraraley');
        expect($newRecord->location)->toBe('不確定');
    });

    it('shows correct verification results after migration', function () {
        // 建立 3 筆沒有捕獲紀錄的魚類
        Fish::factory()->count(3)->create();

        // 建立 2 筆已有捕獲紀錄的魚類
        $fishWithRecords = Fish::factory()->count(2)->create();
        foreach ($fishWithRecords as $fish) {
            CaptureRecord::factory()->create(['fish_id' => $fish->id]);
        }

        // 執行遷移
        $this->artisan('migrate:legacy-fish')
            ->expectsOutputToContain('剩餘未處理的魚類: 0')
            ->expectsOutputToContain('已有捕獲紀錄的魚類: 5')
            ->assertExitCode(0);

        // 驗證結果
        expect(Fish::doesntHave('captureRecords')->count())->toBe(0);
        expect(Fish::has('captureRecords')->count())->toBe(5);
    });
});
