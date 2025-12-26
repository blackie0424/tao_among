<?php

/**
 * S3StorageService Unit Tests
 * 
 * @phpstan-ignore-file
 */

use App\Services\S3StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    // 設定測試用的 S3 設定
    Config::set('storage.drivers.s3.folders', [
        'image' => 'test-images',
        'audio' => 'test-audio',
        'webp' => 'test-webp',
    ]);
    
    // 設定 filesystems.disks.s3 以支援簽章 URL 測試
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

    // 建立測試服務實例
    /** @var \App\Services\S3StorageService service */
    $this->service = new S3StorageService();

    // 使用 fake S3 disk
    Storage::fake('s3');
});

describe('S3StorageService - 資料夾路徑', function () {
    it('應回傳正確的圖片資料夾路徑', function () {
        expect($this->service->getImageFolder())->toBe('test-images');
    });

    it('應回傳正確的音檔資料夾路徑', function () {
        expect($this->service->getAudioFolder())->toBe('test-audio');
    });

    it('應回傳正確的 WebP 資料夾路徑', function () {
        expect($this->service->getWebpFolder())->toBe('test-webp');
    });
});

describe('S3StorageService - getUrl', function () {
    it('應為圖片生成正確的 URL', function () {
        $url = $this->service->getUrl('image', 'test.jpg', false);
        
        expect($url)->toContain('test-images/test.jpg');
    });

    it('應為音檔生成正確的 URL', function () {
        $url = $this->service->getUrl('audio', 'test.mp3');
        
        expect($url)->toContain('test-audio/test.mp3');
    });

    it('當有 webp 時應優先使用 webp URL', function () {
        $url = $this->service->getUrl('image', 'test.jpg', true);
        
        expect($url)->toContain('test-webp/test.webp');
    });

    it('應對無效類型拋出例外', function () {
        $this->service->getUrl('invalid', 'test.txt');
    })->throws(\InvalidArgumentException::class, 'Invalid type: invalid');
});

describe('S3StorageService - uploadFile', function () {
    it('應成功上傳檔案', function () {
        $file = UploadedFile::fake()->image('test.jpg');
        $path = 'test-images/test.jpg';

        $result = $this->service->uploadFile($file, $path);

        expect($result)->toBe($path);
        Storage::disk('s3')->assertExists($path);
    });

    it('上傳失敗時應拋出例外', function () {
        Storage::shouldReceive('disk->putFileAs')
            ->andReturn(false);

        $file = UploadedFile::fake()->image('test.jpg');
        
        $this->service->uploadFile($file, 'test-images/test.jpg');
    })->throws(\RuntimeException::class);
});

describe('S3StorageService - delete', function () {
    it('應成功刪除存在的檔案', function () {
        $path = 'test-images/test.jpg';
        Storage::disk('s3')->put($path, 'test content');

        $result = $this->service->delete($path);

        expect($result)->toBeTrue();
        Storage::disk('s3')->assertMissing($path);
    });

    it('刪除不存在的檔案應回傳 true (S3 行為)', function () {
        $result = $this->service->delete('non-existent.jpg');

        expect($result)->toBeTrue();
    });
});

describe('S3StorageService - deleteWithValidation', function () {
    it('應成功刪除存在的檔案', function () {
        $path = 'test-images/test.jpg';
        Storage::disk('s3')->put($path, 'test content');

        $result = $this->service->deleteWithValidation($path);

        expect($result)->toBe([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
        Storage::disk('s3')->assertMissing($path);
    });

    it('檔案不存在時應回傳失敗訊息', function () {
        $result = $this->service->deleteWithValidation('non-existent.jpg');

        expect($result)->toBe([
            'success' => false,
            'message' => 'File not found: non-existent.jpg'
        ]);
    });
});

describe('S3StorageService - moveObject', function () {
    it('應成功移動檔案', function () {
        $source = 'test-images/old.jpg';
        $dest = 'test-images/new.jpg';
        
        Storage::disk('s3')->put($source, 'test content');

        $result = $this->service->moveObject($source, $dest);

        expect($result)->toBe($dest);
        Storage::disk('s3')->assertMissing($source);
        Storage::disk('s3')->assertExists($dest);
    });

    it('來源檔案不存在時應回傳 null', function () {
        $result = $this->service->moveObject('non-existent.jpg', 'dest.jpg');

        expect($result)->toBeNull();
    });
});

describe('S3StorageService - createSignedUploadUrl', function () {
    it('應生成簽章 URL', function () {
        $path = 'test-images/test.jpg';
        
        $url = $this->service->createSignedUploadUrl($path, 3600);

        expect($url)->toBeString();
        expect($url)->toContain('test-images');
    });

    it('應接受自訂有效期限', function () {
        $path = 'test-images/test.jpg';
        
        $url = $this->service->createSignedUploadUrl($path, 7200);

        expect($url)->toBeString();
    });
});

describe('S3StorageService - createSignedUploadUrlForPendingAudio', function () {
    it('應為待審音檔生成完整資料', function () {
        $result = $this->service->createSignedUploadUrlForPendingAudio(123, 'mp3', 3600);

        expect($result)->toBeArray()
            ->toHaveKeys(['url', 'path', 'filename']);
        
        expect($result['filename'])->toContain('audio_fish_123_');
        expect($result['filename'])->toEndWith('.mp3');
        expect($result['path'])->toStartWith('test-audio/');
        expect($result['url'])->toBeString();
    });

    it('應接受不同的副檔名', function () {
        $result = $this->service->createSignedUploadUrlForPendingAudio(456, 'ogg');

        expect($result['filename'])->toEndWith('.ogg');
    });
});
