<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MigrateLegacyFishCommand', function () {
    
    it('shows success message when all fish images are already in capture records', function () {
        // 建立魚類和捕獲紀錄，圖片路徑相同
        $fish = Fish::factory()->create(['image' => 'test-image.jpg']);
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'test-image.jpg'  // 使用相同的圖片
        ]);

        $this->artisan('migrate:legacy-fish')
            ->expectsOutput('✅ 所有魚類的圖片都已存在於捕獲紀錄中！無需處理。')
            ->assertExitCode(0);
    });

    it('can preview migration with dry-run option', function () {
        // 建立魚類，圖片尚未加入捕獲紀錄
        $fish = Fish::factory()->create([
            'name' => '測試魚',
            'image' => 'test-fish.jpg'
        ]);

        $this->artisan('migrate:legacy-fish', ['--dry-run' => true])
            ->expectsOutput('🔍 DRY-RUN 模式（不會真正寫入資料庫）')
            ->expectsOutputToContain('找到 1 筆魚類的圖片尚未加入捕獲紀錄')
            ->expectsOutputToContain('測試魚')
            ->expectsOutput('⚠️  這只是預覽，尚未寫入資料庫')
            ->assertExitCode(0);

        // 驗證沒有實際建立捕獲紀錄
        expect(CaptureRecord::count())->toBe(0);
    });

    it('creates capture records for fish images not in records', function () {
        // 建立魚類，圖片尚未加入捕獲紀錄
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
            ->expectsOutputToContain('找到 2 筆魚類的圖片尚未加入捕獲紀錄')
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

    it('adds fish image to capture records even if fish already has other records', function () {
        // 建立魚類，已有捕獲紀錄但使用不同圖片
        $fish = Fish::factory()->create([
            'image' => 'fish-original.jpg'
        ]);
        
        // 建立使用不同圖片的捕獲紀錄
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'capture-photo-1.jpg',
            'tribe' => 'ivalino',
            'location' => '已有紀錄的地點',
        ]);

        // 執行遷移
        $this->artisan('migrate:legacy-fish')
            ->expectsOutputToContain('找到 1 筆魚類的圖片尚未加入捕獲紀錄')
            ->assertExitCode(0);

        // 驗證該魚類現在有 2 筆捕獲紀錄
        expect($fish->captureRecords()->count())->toBe(2);

        // 驗證原有紀錄未被修改
        $existingRecord = CaptureRecord::where('image_path', 'capture-photo-1.jpg')->first();
        expect($existingRecord->tribe)->toBe('ivalino');
        expect($existingRecord->location)->toBe('已有紀錄的地點');

        // 驗證新建立的紀錄使用魚類的圖片
        $newRecord = CaptureRecord::where('image_path', 'fish-original.jpg')->first();
        expect($newRecord)->not->toBeNull();
        expect($newRecord->tribe)->toBe('iraraley');
        expect($newRecord->location)->toBe('不確定');
    });

    it('does not create duplicate records for same fish image', function () {
        // 建立魚類，其圖片已經在捕獲紀錄中
        $fish = Fish::factory()->create([
            'image' => 'same-image.jpg'
        ]);
        
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'same-image.jpg',  // 使用相同圖片
        ]);

        // 執行遷移
        $this->artisan('migrate:legacy-fish')
            ->expectsOutput('✅ 所有魚類的圖片都已存在於捕獲紀錄中！無需處理。')
            ->assertExitCode(0);

        // 驗證沒有建立重複的紀錄
        expect($fish->captureRecords()->count())->toBe(1);
        expect(CaptureRecord::where('image_path', 'same-image.jpg')->count())->toBe(1);
    });
});
