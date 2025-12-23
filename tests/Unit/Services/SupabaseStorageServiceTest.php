<?php

use App\Services\SupabaseStorageService;

/**
 * SupabaseStorageService 單元測試
 *
 * 測試目標：確保服務能根據環境變數正確產生不同的檔案路徑
 * - 測試 SUPABASE_IMAGE_FOLDER 參數影響
 * - 測試 SUPABASE_AUDIO_FOLDER 參數影響
 * - 測試 SUPABASE_WEBP_FOLDER 參數影響
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    // 設定基礎環境變數
    putenv('SUPABASE_STORAGE_URL=https://test.supabase.co/storage/v1');
    putenv('SUPABASE_SERVICE_ROLE_KEY=test-key');
    putenv('SUPABASE_BUCKET=test-bucket');
});

describe('getUrl 方法 - 圖片路徑測試', function () {
    
    it('使用預設 images 資料夾當環境變數未設定時', function () {
        // 清除環境變數，使用預設值
        putenv('SUPABASE_IMAGE_FOLDER');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'test-fish.jpg', false);
        
        expect($url)
            ->toContain('/images/test-fish.jpg')
            ->toContain('object/public');
    });
    
    it('使用 dev-images 資料夾當本地開發環境', function () {
        putenv('SUPABASE_IMAGE_FOLDER=dev-images');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'test-fish.jpg', false);
        
        expect($url)
            ->toContain('/dev-images/test-fish.jpg')
            ->not->toContain('/images/test-fish.jpg');
    });
    
    it('使用 prod-images 資料夾當正式環境', function () {
        putenv('SUPABASE_IMAGE_FOLDER=prod-images');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'production-fish.jpg', false);
        
        expect($url)
            ->toContain('/prod-images/production-fish.jpg')
            ->not->toContain('/images/production-fish.jpg');
    });
    
    it('使用自訂資料夾名稱', function () {
        putenv('SUPABASE_IMAGE_FOLDER=custom/nested/folder');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'custom-fish.jpg', false);
        
        expect($url)->toContain('/custom/nested/folder/custom-fish.jpg');
    });
});

describe('getUrl 方法 - 音訊路徑測試', function () {
    
    it('使用預設 audio 資料夾當環境變數未設定時', function () {
        putenv('SUPABASE_AUDIO_FOLDER');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('audios', 'test-sound.mp3', null);
        
        expect($url)
            ->toContain('/audio/test-sound.mp3')
            ->toContain('object/public');
    });
    
    it('使用 dev-audio 資料夾當本地開發環境', function () {
        putenv('SUPABASE_AUDIO_FOLDER=dev-audio');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('audio', 'dev-sound.mp3', null);
        
        expect($url)
            ->toContain('/dev-audio/dev-sound.mp3')
            ->not->toContain('/audio/dev-sound.mp3');
    });
    
    it('使用 prod-audio 資料夾當正式環境', function () {
        putenv('SUPABASE_AUDIO_FOLDER=prod-audio');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('audios', 'prod-sound.webm', null);
        
        expect($url)
            ->toContain('/prod-audio/prod-sound.webm')
            ->not->toContain('/audio/prod-sound.webm');
    });
    
    it('audio 和 audios 類型都使用相同的資料夾', function () {
        putenv('SUPABASE_AUDIO_FOLDER=shared-audio');
        
        $service = new SupabaseStorageService();
        $url1 = $service->getUrl('audio', 'test.mp3', null);
        $url2 = $service->getUrl('audios', 'test.mp3', null);
        
        expect($url1)->toBe($url2);
        expect($url1)->toContain('/shared-audio/test.mp3');
    });
});

describe('getUrl 方法 - WebP 路徑測試', function () {
    
    it('使用預設 webp 資料夾當環境變數未設定時', function () {
        putenv('SUPABASE_WEBP_FOLDER');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'test-fish.jpg', true);
        
        expect($url)
            ->toContain('/webp/test-fish.webp')
            ->toContain('object/public');
    });
    
    it('使用 dev-webp 資料夾當本地開發環境', function () {
        putenv('SUPABASE_WEBP_FOLDER=dev-webp');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'dev-fish.jpg', true);
        
        expect($url)
            ->toContain('/dev-webp/dev-fish.webp')
            ->not->toContain('/webp/dev-fish.webp');
    });
    
    it('使用 prod-webp 資料夾當正式環境', function () {
        putenv('SUPABASE_WEBP_FOLDER=prod-webp');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'prod-fish.png', true);
        
        expect($url)
            ->toContain('/prod-webp/prod-fish.webp')
            ->not->toContain('/webp/prod-fish.webp');
    });
    
    it('WebP 檔案名稱正確移除原副檔名並加上 .webp', function () {
        putenv('SUPABASE_WEBP_FOLDER=webp-folder');
        
        $service = new SupabaseStorageService();
        $url1 = $service->getUrl('images', 'fish.jpg', true);
        $url2 = $service->getUrl('images', 'fish.png', true);
        $url3 = $service->getUrl('images', 'fish.jpeg', true);
        
        expect($url1)->toContain('/webp-folder/fish.webp');
        expect($url2)->toContain('/webp-folder/fish.webp');
        expect($url3)->toContain('/webp-folder/fish.webp');
    });
});

describe('getUrl 方法 - 混合情境測試', function () {
    
    it('同時使用不同的資料夾設定', function () {
        putenv('SUPABASE_IMAGE_FOLDER=dev-images');
        putenv('SUPABASE_AUDIO_FOLDER=dev-audio');
        putenv('SUPABASE_WEBP_FOLDER=dev-webp');
        
        $service = new SupabaseStorageService();
        
        $imageUrl = $service->getUrl('images', 'fish.jpg', false);
        $audioUrl = $service->getUrl('audio', 'sound.mp3', null);
        $webpUrl = $service->getUrl('images', 'fish.jpg', true);
        
        expect($imageUrl)->toContain('/dev-images/fish.jpg');
        expect($audioUrl)->toContain('/dev-audio/sound.mp3');
        expect($webpUrl)->toContain('/dev-webp/fish.webp');
    });
    
    it('歷史資料的完整 URL 直接回傳不受資料夾設定影響', function () {
        putenv('SUPABASE_IMAGE_FOLDER=dev-images');
        
        $service = new SupabaseStorageService();
        $legacyUrl = 'https://example.com/storage/old-fish.jpg';
        $result = $service->getUrl('images', $legacyUrl, false);
        
        expect($result)->toBe($legacyUrl);
    });
    
    it('hasWebp 為 false 時使用 imageFolder，為 true 時使用 webpFolder', function () {
        putenv('SUPABASE_IMAGE_FOLDER=img');
        putenv('SUPABASE_WEBP_FOLDER=wp');
        
        $service = new SupabaseStorageService();
        
        $originalUrl = $service->getUrl('images', 'test.jpg', false);
        $webpUrl = $service->getUrl('images', 'test.jpg', true);
        
        expect($originalUrl)->toContain('/img/test.jpg');
        expect($webpUrl)->toContain('/wp/test.webp');
    });
});

describe('環境變數預設值測試', function () {
    
    it('所有資料夾參數都未設定時使用預設值', function () {
        putenv('SUPABASE_IMAGE_FOLDER');
        putenv('SUPABASE_AUDIO_FOLDER');
        putenv('SUPABASE_WEBP_FOLDER');
        
        $service = new SupabaseStorageService();
        
        $imageUrl = $service->getUrl('images', 'test.jpg', false);
        $audioUrl = $service->getUrl('audio', 'test.mp3', null);
        $webpUrl = $service->getUrl('images', 'test.jpg', true);
        
        expect($imageUrl)->toContain('/images/test.jpg');
        expect($audioUrl)->toContain('/audio/test.mp3');
        expect($webpUrl)->toContain('/webp/test.webp');
    });
});

describe('路徑格式驗證', function () {
    
    it('產生的 URL 結構正確', function () {
        putenv('SUPABASE_IMAGE_FOLDER=test-img');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'fish.jpg', false);
        
        // 驗證 URL 基本結構
        expect($url)
            ->toStartWith('https://')
            ->toContain('/object/public/')
            ->toContain('/test-img/')
            ->toEndWith('/fish.jpg');
        
        // 驗證 URL 各部分順序正確
        $pattern = '#^https?://[^/]+/object/public/[^/]+/test-img/fish\.jpg$#';
        expect($url)->toMatch($pattern);
    });
    
    it('路徑中不應包含雙斜線', function () {
        putenv('SUPABASE_IMAGE_FOLDER=images');
        
        $service = new SupabaseStorageService();
        $url = $service->getUrl('images', 'fish.jpg', false);
        
        // 移除協議部分的 :// 後檢查
        $pathPart = preg_replace('#^https?://#', '', $url);
        expect($pathPart)->not->toContain('//');
    });
});

afterEach(function () {
    // 清理環境變數
    putenv('SUPABASE_IMAGE_FOLDER');
    putenv('SUPABASE_AUDIO_FOLDER');
    putenv('SUPABASE_WEBP_FOLDER');
    putenv('SUPABASE_STORAGE_URL');
    putenv('SUPABASE_SERVICE_ROLE_KEY');
    putenv('SUPABASE_BUCKET');
});
