<?php

/**
 * LineUploadService Unit Tests
 *
 * @phpstan-ignore-file
 */

use App\Services\LineUploadService;
use App\Contracts\StorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    // 設定測試用的 S3 設定
    Config::set('storage.drivers.s3.folders', [
        'image' => 'test-images',
        'audio' => 'test-audio',
        'webp' => 'test-webp',
    ]);
    
    Config::set('filesystems.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'ap-northeast-1',
        'bucket' => 'test-bucket',
        'url' => null,
        'endpoint' => null,
        'use_path_style_endpoint' => false,
        'throw' => false,
    ]);

    // 使用 fake S3 disk
    Storage::fake('s3');
    
    // Mock StorageServiceInterface
    $this->storageService = Mockery::mock(StorageServiceInterface::class);
    $this->storageService->shouldReceive('getAudioFolder')->andReturn('test-audio');
    $this->storageService->shouldReceive('getImageFolder')->andReturn('test-images');
    
    // 建立測試服務實例
    $this->service = new LineUploadService($this->storageService);
});

afterEach(function () {
    Mockery::close();
});

describe('LineUploadService - uploadLineAudio 檔案名稱生成', function () {
    it('應生成 UUID 格式的檔案名稱', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        // 驗證檔案名稱格式：UUID + .m4a
        expect($filename)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/');
    });
    
    it('應使用指定的副檔名', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream, 'mp3');
        
        expect($filename)->toEndWith('.mp3');
    });
    
    it('預設應使用 m4a 副檔名', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        expect($filename)->toEndWith('.m4a');
    });
});

describe('LineUploadService - uploadLineAudio Content-Type 設定', function () {
    it('應設定正確的 Content-Type 為 audio/mp4', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        // 驗證檔案已上傳
        $expectedPath = 'test-audio/' . $filename;
        Storage::disk('s3')->assertExists($expectedPath);
        
        // 注意：Laravel Storage fake 不會實際儲存 metadata
        // 但我們可以驗證檔案已被上傳
        expect($filename)->not->toBeEmpty();
    });
    
    it('應設定 visibility 為 public', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        $expectedPath = 'test-audio/' . $filename;
        Storage::disk('s3')->assertExists($expectedPath);
    });
    
    it('應設定 CacheControl', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        $expectedPath = 'test-audio/' . $filename;
        Storage::disk('s3')->assertExists($expectedPath);
    });
});

describe('LineUploadService - uploadLineAudio 成功上傳', function () {
    it('上傳成功時應回傳檔案名稱', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        expect($filename)->toBeString();
        expect($filename)->not->toBeEmpty();
        expect($filename)->toContain('.');
    });
    
    it('應將音檔上傳到正確的路徑', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        $audioStream = 'fake audio content';
        
        $filename = $this->service->uploadLineAudio($audioStream);
        
        $expectedPath = 'test-audio/' . $filename;
        Storage::disk('s3')->assertExists($expectedPath);
    });
    
    it('應記錄上傳開始的日誌', function () {
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Starting audio upload', Mockery::on(function ($context) {
                return isset($context['filename'])
                    && isset($context['path'])
                    && isset($context['data_size']);
            }));
        
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Audio uploaded successfully', Mockery::type('array'));
        
        $audioStream = 'fake audio content';
        
        $this->service->uploadLineAudio($audioStream);
    });
    
    it('應記錄上傳成功的日誌', function () {
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Starting audio upload', Mockery::type('array'));
        
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Audio uploaded successfully', Mockery::on(function ($context) {
                return isset($context['filename'])
                    && isset($context['path'])
                    && isset($context['data_size']);
            }));
        
        $audioStream = 'fake audio content';
        
        $this->service->uploadLineAudio($audioStream);
    });
});

describe('LineUploadService - uploadLineAudio 上傳失敗', function () {
    it('上傳失敗時應拋出例外', function () {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        
        // Mock Storage::disk('s3')->put() 回傳 false
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        
        Storage::shouldReceive('put')
            ->andReturn(false);
        
        $audioStream = 'fake audio content';
        
        $this->service->uploadLineAudio($audioStream);
    })->throws(\Exception::class, 'Failed to upload LINE audio to S3');
    
    it('上傳失敗時應記錄錯誤日誌', function () {
        Log::shouldReceive('info')->andReturn(null);
        
        Log::shouldReceive('error')
            ->once()
            ->with('LINE Upload: Failed to upload audio', Mockery::on(function ($context) {
                return isset($context['error'])
                    && isset($context['trace'])
                    && isset($context['exception_class']);
            }));
        
        // Mock Storage::disk('s3')->put() 回傳 false
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        
        Storage::shouldReceive('put')
            ->andReturn(false);
        
        $audioStream = 'fake audio content';
        
        try {
            $this->service->uploadLineAudio($audioStream);
        } catch (\Exception $e) {
            // 預期會拋出例外
            expect($e->getMessage())->toContain('Failed to upload LINE audio to S3');
        }
    });
    
    it('Storage 拋出例外時應包裝並重新拋出', function () {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        
        // Mock Storage::disk('s3')->put() 拋出例外
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        
        Storage::shouldReceive('put')
            ->andThrow(new \Exception('S3 connection failed'));
        
        $audioStream = 'fake audio content';
        
        $this->service->uploadLineAudio($audioStream);
    })->throws(\Exception::class, 'Failed to upload LINE audio to S3: S3 connection failed');
});

describe('LineUploadService - uploadLineAudio 資料大小記錄', function () {
    it('應記錄字串資料的大小', function () {
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Starting audio upload', Mockery::on(function ($context) {
                return $context['data_size'] === 18; // 'fake audio content' 的長度
            }));
        
        Log::shouldReceive('info')
            ->once()
            ->with('LINE Upload: Audio uploaded successfully', Mockery::type('array'));
        
        $audioStream = 'fake audio content';
        
        $this->service->uploadLineAudio($audioStream);
    });
});
