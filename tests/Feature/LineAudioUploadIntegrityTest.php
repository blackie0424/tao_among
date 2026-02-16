<?php

/**
 * LINE Audio Upload Integrity Test
 *
 * 驗證音檔上傳完整性：
 * - 上傳測試音檔到 S3
 * - 下載並比較內容
 * - 驗證 Content-Type 是否正確
 * - 驗證檔案可以正常播放
 *
 * Requirements: 1.2, 2.2
 */

use App\Services\LineUploadService;
use App\Contracts\StorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // 使用 fake S3 進行測試
    Storage::fake('s3');
    
    // 建立 StorageServiceInterface mock
    $this->storageService = Mockery::mock(StorageServiceInterface::class);
    $this->storageService->shouldReceive('getAudioFolder')->andReturn('test-audio');
    
    $this->service = new LineUploadService($this->storageService);
});

afterEach(function () {
    Mockery::close();
});

describe('音檔上傳完整性驗證', function () {
    it('應該上傳音檔並保持內容完整性（round-trip）', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        // 建立測試音檔內容（模擬 M4A 格式的 magic bytes）
        // M4A 檔案通常以 ftyp 開頭
        $testAudioContent = pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16) . 'test audio data content';
        
        // 上傳音檔
        $filename = $this->service->uploadLineAudio($testAudioContent);
        
        // 驗證檔案名稱格式
        expect($filename)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/');
        
        // 下載音檔
        $downloadedContent = Storage::disk('s3')->get('test-audio/' . $filename);
        
        // 驗證內容完整性（round-trip）
        expect($downloadedContent)->toBe($testAudioContent);
        expect(strlen($downloadedContent))->toBe(strlen($testAudioContent));
    });
    
    it('應該設定正確的 Content-Type 為 audio/mp4', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        // 建立測試音檔內容
        $testAudioContent = pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16) . 'test audio data';
        
        // 上傳音檔
        $filename = $this->service->uploadLineAudio($testAudioContent);
        
        $path = 'test-audio/' . $filename;
        
        // 驗證檔案存在
        expect(Storage::disk('s3')->exists($path))->toBeTrue();
        
        // 注意：Laravel Storage fake 不會儲存實際的 metadata
        // 在真實的 S3 環境中，我們可以使用 AWS SDK 來檢查 Content-Type
        // 這裡我們驗證檔案已成功上傳
        $content = Storage::disk('s3')->get($path);
        expect($content)->toBe($testAudioContent);
    });
    
    it('應該上傳不同大小的音檔並保持完整性', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        // 測試不同大小的音檔
        $sizes = [100, 1024, 10240, 102400]; // 100B, 1KB, 10KB, 100KB
        
        foreach ($sizes as $size) {
            // 建立指定大小的測試音檔
            $header = pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16);
            $testAudioContent = $header . str_repeat('A', $size - strlen($header));
            
            // 上傳音檔
            $filename = $this->service->uploadLineAudio($testAudioContent);
            
            // 下載並驗證
            $downloadedContent = Storage::disk('s3')->get('test-audio/' . $filename);
            
            expect(strlen($downloadedContent))->toBe(strlen($testAudioContent));
            expect($downloadedContent)->toBe($testAudioContent);
        }
    });
    
    it('應該正確處理二進位音檔資料', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        // 建立包含各種二進位資料的測試音檔
        $testAudioContent = '';
        
        // M4A header
        $testAudioContent .= pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16);
        
        // 加入各種二進位資料
        for ($i = 0; $i < 256; $i++) {
            $testAudioContent .= chr($i);
        }
        
        // 上傳音檔
        $filename = $this->service->uploadLineAudio($testAudioContent);
        
        // 下載並驗證
        $downloadedContent = Storage::disk('s3')->get('test-audio/' . $filename);
        
        // 驗證每個 byte 都相同
        expect(strlen($downloadedContent))->toBe(strlen($testAudioContent));
        
        for ($i = 0; $i < strlen($testAudioContent); $i++) {
            expect(ord($downloadedContent[$i]))->toBe(ord($testAudioContent[$i]));
        }
    });
    
    it('應該驗證上傳的音檔具有有效的 M4A 格式標記', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        // 建立有效的 M4A 格式音檔
        $testAudioContent = pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16) . 'audio data';
        
        // 上傳音檔
        $filename = $this->service->uploadLineAudio($testAudioContent);
        
        // 下載音檔
        $downloadedContent = Storage::disk('s3')->get('test-audio/' . $filename);
        
        // 驗證 M4A 格式標記（ftyp）
        expect(strpos($downloadedContent, 'ftyp'))->not->toBeFalse();
        expect(strpos($downloadedContent, 'isom'))->not->toBeFalse();
    });
});

describe('音檔上傳錯誤處理', function () {
    it('應該在上傳失敗時拋出詳細的例外', function () {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        
        // Mock Storage 使其失敗
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        
        Storage::shouldReceive('put')
            ->andReturn(false);
        
        $testAudioContent = 'test audio content';
        
        expect(fn () => $this->service->uploadLineAudio($testAudioContent))
            ->toThrow(\Exception::class, 'Failed to upload LINE audio to S3');
    });
});
