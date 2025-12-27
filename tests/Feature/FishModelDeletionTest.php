<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\CaptureRecord;
use App\Contracts\StorageServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

describe('Fish Model Deletion - Storage File Cleanup', function () {
    
    beforeEach(function () {
        // 使用 fake S3 storage
        Storage::fake('s3');
        
        // 設定 storage driver 為 s3
        Config::set('storage.default', 's3');
        Config::set('storage.drivers.s3.folders', [
            'image' => 'images',
            'audio' => 'audio',
            'webp' => 'webp',
        ]);
    });

    it('刪除魚類時應該刪除圖片檔案', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        
        // 建立測試檔案
        Storage::disk('s3')->put($imageFolder . '/test-fish.jpg', 'fake image content');
        
        // 建立魚類記錄
        $fish = Fish::factory()->create([
            'image' => 'test-fish.jpg',
            'has_webp' => false
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/test-fish.jpg');
        
        // 刪除魚類
        $fish->delete();
        
        // 驗證圖片檔案被刪除
        Storage::disk('s3')->assertMissing($imageFolder . '/test-fish.jpg');
    });

    it('刪除魚類時應該同時刪除 WebP 版本圖片', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        $webpFolder = $storage->getWebpFolder();
        
        // 建立測試檔案
        Storage::disk('s3')->put($imageFolder . '/test-fish.jpg', 'fake image content');
        Storage::disk('s3')->put($webpFolder . '/test-fish.webp', 'fake webp content');
        
        // 建立魚類記錄（標記有 WebP）
        $fish = Fish::factory()->create([
            'image' => 'test-fish.jpg',
            'has_webp' => true
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/test-fish.jpg');
        Storage::disk('s3')->assertExists($webpFolder . '/test-fish.webp');
        
        // 刪除魚類
        $fish->delete();
        
        // 驗證兩個檔案都被刪除
        Storage::disk('s3')->assertMissing($imageFolder . '/test-fish.jpg');
        Storage::disk('s3')->assertMissing($webpFolder . '/test-fish.webp');
    });

    it('刪除魚類時不應該刪除 default.png', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        
        // 建立 default.png 檔案
        Storage::disk('s3')->put($imageFolder . '/default.png', 'default image content');
        
        // 建立使用 default.png 的魚類
        $fish = Fish::factory()->create([
            'image' => 'default.png',
            'has_webp' => false
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/default.png');
        
        // 刪除魚類
        $fish->delete();
        
        // 驗證 default.png 仍然存在（不應該被刪除）
        Storage::disk('s3')->assertExists($imageFolder . '/default.png');
    });

    it('刪除魚類時應該透過串聯刪除來刪除相關音頻檔案', function () {
        $storage = app(StorageServiceInterface::class);
        $audioFolder = $storage->getAudioFolder();
        
        // 建立魚類
        $fish = Fish::factory()->create();
        
        // 建立測試音頻檔案
        Storage::disk('s3')->put($audioFolder . '/test-audio-1.mp3', 'fake audio content 1');
        Storage::disk('s3')->put($audioFolder . '/test-audio-2.mp3', 'fake audio content 2');
        
        // 建立音頻記錄
        FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'name' => 'Test Audio 1',
            'locate' => 'test-audio-1.mp3'
        ]);
        
        FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'name' => 'Test Audio 2',
            'locate' => 'test-audio-2.mp3'
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($audioFolder . '/test-audio-1.mp3');
        Storage::disk('s3')->assertExists($audioFolder . '/test-audio-2.mp3');
        
        // 刪除魚類（應該觸發 FishAudio 的 deleting 事件）
        $fish->delete();
        
        // 驗證音頻檔案被刪除（透過 FishAudio::deleting 事件）
        Storage::disk('s3')->assertMissing($audioFolder . '/test-audio-1.mp3');
        Storage::disk('s3')->assertMissing($audioFolder . '/test-audio-2.mp3');
        
        // 驗證音頻記錄被軟刪除
        $this->assertSoftDeleted('fish_audios', ['fish_id' => $fish->id]);
    });

    it('刪除魚類時應該透過串聯刪除來刪除捕獲記錄的圖片', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        
        // 建立魚類
        $fish = Fish::factory()->create();
        
        // 建立測試圖片檔案
        Storage::disk('s3')->put($imageFolder . '/capture-1.jpg', 'fake capture image 1');
        Storage::disk('s3')->put($imageFolder . '/capture-2.jpg', 'fake capture image 2');
        
        // 建立捕獲記錄
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'capture-1.jpg'
        ]);
        
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'capture-2.jpg'
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/capture-1.jpg');
        Storage::disk('s3')->assertExists($imageFolder . '/capture-2.jpg');
        
        // 刪除魚類（應該觸發 CaptureRecord 的 deleting 事件）
        $fish->delete();
        
        // 驗證捕獲記錄圖片被刪除（透過 CaptureRecord::deleting 事件）
        Storage::disk('s3')->assertMissing($imageFolder . '/capture-1.jpg');
        Storage::disk('s3')->assertMissing($imageFolder . '/capture-2.jpg');
        
        // 驗證捕獲記錄被軟刪除
        $this->assertSoftDeleted('capture_records', ['fish_id' => $fish->id]);
    });

    it('刪除魚類時應該正確處理空的音頻檔案路徑', function () {
        $fish = Fish::factory()->create();
        
        // 建立音頻記錄，使用預設的 locate 值（不是 null）
        $audio = FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'name' => 'Test Audio',
        ]);
        
        // 手動設定 locate 為空字串（模擬沒有檔案的情況）
        $audio->update(['locate' => '']);
        
        // 刪除魚類（不應該拋出例外）
        expect(fn () => $fish->delete())->not->toThrow(Exception::class);
        
        // 驗證音頻記錄被軟刪除
        $this->assertSoftDeleted('fish_audios', ['fish_id' => $fish->id]);
    });

    it('刪除魚類時應該正確處理空的捕獲記錄圖片路徑', function () {
        $fish = Fish::factory()->create();
        
        // 建立捕獲記錄，使用預設的 image_path
        $record = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
        ]);
        
        // 手動設定 image_path 為空字串
        $record->update(['image_path' => '']);
        
        // 刪除魚類（不應該拋出例外）
        expect(fn () => $fish->delete())->not->toThrow(Exception::class);
        
        // 驗證捕獲記錄被軟刪除
        $this->assertSoftDeleted('capture_records', ['fish_id' => $fish->id]);
    });

    it('刪除魚類時應該處理多種類型的關聯資料和檔案', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        $webpFolder = $storage->getWebpFolder();
        $audioFolder = $storage->getAudioFolder();
        
        // 建立所有類型的測試檔案
        Storage::disk('s3')->put($imageFolder . '/fish-main.jpg', 'fish image');
        Storage::disk('s3')->put($webpFolder . '/fish-main.webp', 'fish webp');
        Storage::disk('s3')->put($audioFolder . '/fish-audio.mp3', 'fish audio');
        Storage::disk('s3')->put($imageFolder . '/capture-img.jpg', 'capture image');
        
        // 建立魚類
        $fish = Fish::factory()->create([
            'image' => 'fish-main.jpg',
            'has_webp' => true
        ]);
        
        // 建立關聯資料
        FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'locate' => 'fish-audio.mp3'
        ]);
        
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'capture-img.jpg'
        ]);
        
        // 驗證所有檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/fish-main.jpg');
        Storage::disk('s3')->assertExists($webpFolder . '/fish-main.webp');
        Storage::disk('s3')->assertExists($audioFolder . '/fish-audio.mp3');
        Storage::disk('s3')->assertExists($imageFolder . '/capture-img.jpg');
        
        // 刪除魚類
        $fish->delete();
        
        // 驗證所有相關檔案都被刪除
        Storage::disk('s3')->assertMissing($imageFolder . '/fish-main.jpg');
        Storage::disk('s3')->assertMissing($webpFolder . '/fish-main.webp');
        Storage::disk('s3')->assertMissing($audioFolder . '/fish-audio.mp3');
        Storage::disk('s3')->assertMissing($imageFolder . '/capture-img.jpg');
        
        // 驗證所有關聯資料被軟刪除
        $this->assertSoftDeleted('fish', ['id' => $fish->id]);
        $this->assertSoftDeleted('fish_audios', ['fish_id' => $fish->id]);
        $this->assertSoftDeleted('capture_records', ['fish_id' => $fish->id]);
    });
});

describe('FishAudio Model Deletion - Storage File Cleanup', function () {
    
    beforeEach(function () {
        Storage::fake('s3');
        Config::set('storage.default', 's3');
        Config::set('storage.drivers.s3.folders', [
            'image' => 'images',
            'audio' => 'audio',
            'webp' => 'webp',
        ]);
    });

    it('刪除音頻記錄時應該刪除對應的音頻檔案', function () {
        $storage = app(StorageServiceInterface::class);
        $audioFolder = $storage->getAudioFolder();
        
        // 建立魚類和音頻檔案
        $fish = Fish::factory()->create();
        Storage::disk('s3')->put($audioFolder . '/test-audio.mp3', 'fake audio content');
        
        $audio = FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'name' => 'Test Audio',
            'locate' => 'test-audio.mp3'
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($audioFolder . '/test-audio.mp3');
        
        // 刪除音頻記錄
        $audio->delete();
        
        // 驗證音頻檔案被刪除
        Storage::disk('s3')->assertMissing($audioFolder . '/test-audio.mp3');
    });

    it('刪除音頻記錄時應該正確處理空的檔案路徑', function () {
        $fish = Fish::factory()->create();
        
        $audio = FishAudio::factory()->create([
            'fish_id' => $fish->id,
            'name' => 'Test Audio',
        ]);
        
        // 手動設定 locate 為空字串
        $audio->update(['locate' => '']);
        
        // 刪除音頻記錄（不應該拋出例外）
        expect(fn () => $audio->delete())->not->toThrow(Exception::class);
        
        // 驗證記錄被軟刪除
        $this->assertSoftDeleted('fish_audios', ['id' => $audio->id]);
    });
});

describe('CaptureRecord Model Deletion - Storage File Cleanup', function () {
    
    beforeEach(function () {
        Storage::fake('s3');
        Config::set('storage.default', 's3');
        Config::set('storage.drivers.s3.folders', [
            'image' => 'images',
            'audio' => 'audio',
            'webp' => 'webp',
        ]);
    });

    it('刪除捕獲記錄時應該刪除對應的圖片檔案', function () {
        $storage = app(StorageServiceInterface::class);
        $imageFolder = $storage->getImageFolder();
        
        // 建立魚類和圖片檔案
        $fish = Fish::factory()->create();
        Storage::disk('s3')->put($imageFolder . '/capture-test.jpg', 'fake capture image');
        
        $record = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'capture-test.jpg'
        ]);
        
        // 驗證檔案存在
        Storage::disk('s3')->assertExists($imageFolder . '/capture-test.jpg');
        
        // 刪除捕獲記錄
        $record->delete();
        
        // 驗證圖片檔案被刪除
        Storage::disk('s3')->assertMissing($imageFolder . '/capture-test.jpg');
    });

    it('刪除捕獲記錄時應該正確處理空的圖片路徑', function () {
        $fish = Fish::factory()->create();
        
        $record = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
        ]);
        
        // 手動設定 image_path 為空字串
        $record->update(['image_path' => '']);
        
        // 刪除捕獲記錄（不應該拋出例外）
        expect(fn () => $record->delete())->not->toThrow(Exception::class);
        
        // 驗證記錄被軟刪除
        $this->assertSoftDeleted('capture_records', ['id' => $record->id]);
    });
});
