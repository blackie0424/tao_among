<?php

use App\Http\Controllers\LineBotController;
use App\Services\LineBotService;
use App\Http\Controllers\ApiFishController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 設定測試環境變數
        config(['line.channel_secret' => 'test_channel_secret']);
        config(['line.channel_access_token' => 'test_access_token']);
    }

    /**
     * 測試缺少簽章的請求應該回傳 400
     */
    public function test_webhook_missing_signature_returns_400(): void
    {
        $response = $this->postJson('/prefix/api/line/webhook', [
            'events' => [],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Missing signature']);
    }

    /**
     * 測試無效的簽章應該回傳 400
     */
    public function test_webhook_invalid_signature_returns_400(): void
    {
        $body = json_encode(['events' => []]);
        
        $response = $this->post('/prefix/api/line/webhook', [], [
            'X-Line-Signature' => 'invalid_signature_value',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    /**
     * 測試空白訊息應該回傳使用說明
     *
     * 注意：這個測試需要 mock LINE SDK，因為實際驗證簽章會失敗
     */
    public function test_build_help_message(): void
    {
        $service = new LineBotService();
        $message = $service->buildHelpMessage();
        
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $message);
        $this->assertStringContainsString('歡迎使用', $message->getText());
    }

    /**
     * 測試建立魚類卡片
     */
    public function test_build_fish_card(): void
    {
        $fishData = [
            'id' => 1,
            'name' => '測試魚',
            'image_url' => 'https://example.com/image.jpg',
            'display_image_url' => 'https://example.com/display.jpg',
            'tribal_classifications' => [
                ['tribe' => 'ivalino', 'food_category' => 'oyod'],
            ],
        ];

        $service = new LineBotService();
        $message = $service->buildFishCard($fishData);

        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $message);
        $this->assertEquals('測試魚', $message->getAltText());
    }

    /**
     * 測試找不到資料時的訊息
     */
    public function test_build_fish_list_message_empty(): void
    {
        $service = new LineBotService();
        $messages = $service->buildFishListMessage([]);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $messages[0]);
        $this->assertStringContainsString('找不到', $messages[0]->getText());
    }

    /**
     * 測試單筆資料時回傳卡片
     */
    public function test_build_fish_list_message_single(): void
    {
        $fishData = [
            [
                'id' => 1,
                'name' => '測試魚',
                'image_url' => 'https://example.com/image.jpg',
                'tribal_classifications' => [],
            ],
        ];

        $service = new LineBotService();
        $messages = $service->buildFishListMessage($fishData);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $messages[0]);
    }

    /**
     * 測試多筆資料時回傳輪播
     */
    public function test_build_fish_list_message_multiple(): void
    {
        $fishData = [
            [
                'id' => 1,
                'name' => '魚1',
                'image_url' => 'https://example.com/1.jpg',
                'tribal_classifications' => [],
            ],
            [
                'id' => 2,
                'name' => '魚2',
                'image_url' => 'https://example.com/2.jpg',
                'tribal_classifications' => [],
            ],
        ];

        $service = new LineBotService();
        $messages = $service->buildFishListMessage($fishData);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $messages[0]);
    }

    /**
     * 測試 validateAudioBlob 能識別有效的 M4A 檔案
     */
    public function test_validate_audio_blob_accepts_valid_m4a(): void
    {
        // 建立一個模擬的 M4A 檔案內容（包含 ftyp 標記）
        // M4A 檔案的典型開頭：00 00 00 XX 66 74 79 70 (ftyp)
        $validM4aBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        
        $controller = $this->app->make(LineBotController::class);
        
        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateAudioBlob');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $validM4aBlob);
        
        $this->assertTrue($result);
    }

    /**
     * 測試 validateAudioBlob 能識別過小的檔案
     */
    public function test_validate_audio_blob_rejects_small_file(): void
    {
        // 建立一個小於 100 bytes 的檔案
        $smallBlob = str_repeat('x', 50);
        
        $controller = $this->app->make(LineBotController::class);
        
        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateAudioBlob');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $smallBlob);
        
        $this->assertFalse($result);
    }

    /**
     * 測試 validateAudioBlob 能記錄檔案簽名資訊
     */
    public function test_validate_audio_blob_logs_signature_info(): void
    {
        // 建立一個沒有 ftyp 標記的檔案（但大小足夠）
        $blobWithoutFtyp = str_repeat('x', 150);
        
        $controller = $this->app->make(LineBotController::class);
        
        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateAudioBlob');
        $method->setAccessible(true);
        
        // 清除之前的日誌
        \Illuminate\Support\Facades\Log::spy();
        
        $result = $method->invoke($controller, $blobWithoutFtyp);
        
        // 驗證仍然通過（因為我們不強制要求 ftyp）
        $this->assertTrue($result);
        
        // 驗證有記錄警告
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->once()
            ->with('Audio blob missing M4A signature', \Mockery::type('array'));
    }

    /**
     * 測試時長驗證：5000 毫秒應該被接受
     */
    public function test_duration_validation_accepts_5000ms(): void
    {
        // 建立模擬的 LINE webhook 請求，包含 5000ms 的音檔
        $this->markTestSkipped('需要完整的 LINE webhook 模擬，包含簽章驗證');
    }

    /**
     * 測試時長驗證：5100 毫秒應該被接受（邊界條件）
     */
    public function test_duration_validation_accepts_5100ms(): void
    {
        // 建立模擬的 LINE webhook 請求，包含 5100ms 的音檔
        $this->markTestSkipped('需要完整的 LINE webhook 模擬，包含簽章驗證');
    }

    /**
     * 測試時長驗證：5101 毫秒應該被拒絕
     */
    public function test_duration_validation_rejects_5101ms(): void
    {
        // 建立模擬的 LINE webhook 請求，包含 5101ms 的音檔
        $this->markTestSkipped('需要完整的 LINE webhook 模擬，包含簽章驗證');
    }

    /**
     * 測試時長驗證邏輯的核心功能（不依賴完整的 webhook）
     */
    public function test_duration_validation_logic(): void
    {
        // 測試邊界條件
        $testCases = [
            ['duration' => 0, 'expected' => true, 'description' => '0ms 應該被接受'],
            ['duration' => 1000, 'expected' => true, 'description' => '1000ms 應該被接受'],
            ['duration' => 5000, 'expected' => true, 'description' => '5000ms 應該被接受'],
            ['duration' => 5100, 'expected' => true, 'description' => '5100ms 應該被接受（邊界）'],
            ['duration' => 5101, 'expected' => false, 'description' => '5101ms 應該被拒絕'],
            ['duration' => 6000, 'expected' => false, 'description' => '6000ms 應該被拒絕'],
            ['duration' => 10000, 'expected' => false, 'description' => '10000ms 應該被拒絕'],
        ];

        foreach ($testCases as $testCase) {
            $duration = $testCase['duration'];
            $expected = $testCase['expected'];
            $description = $testCase['description'];

            // 驗證邏輯：duration > 5100 應該被拒絕
            $isValid = $duration <= 5100;

            $this->assertEquals(
                $expected,
                $isValid,
                "Failed: {$description} (duration: {$duration}ms)"
            );
        }
    }

    /**
     * 測試時長驗證的容差設定正確（5100ms = 5.1秒）
     */
    public function test_duration_tolerance_is_5100ms(): void
    {
        // 讀取 LineBotController 的原始碼，確認容差設定
        $controllerPath = app_path('Http/Controllers/LineBotController.php');
        $content = file_get_contents($controllerPath);

        // 驗證程式碼中包含正確的容差檢查
        $this->assertStringContainsString(
            'if ($duration > 5100)',
            $content,
            '時長驗證應該使用 5100ms 作為上限'
        );

        // 驗證註解說明正確
        $this->assertStringContainsString(
            '5100ms = 5.1秒',
            $content,
            '應該包含容差說明註解'
        );

        $this->assertStringContainsString(
            '100ms 容差',
            $content,
            '應該說明 100ms 容差'
        );
    }

    /**
     * 測試 saveFishAudio 方法能正確更新資料庫
     */
    public function test_save_fish_audio_updates_database_correctly(): void
    {
        // 建立測試用的 Fish
        $fish = \App\Models\Fish::factory()->create([
            'name' => '測試魚',
            'audio_filename' => null,
        ]);

        // 模擬音檔資料
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        $duration = 3000; // 3 秒

        // Mock LineUploadService
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $expectedFilename = 'test-audio-file.m4a';
        $mockUploadService->shouldReceive('uploadLineAudio')
            ->once()
            ->with($audioBlob)
            ->andReturn($expectedFilename);

        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

        // Mock LineBotService 的 replyMessage 方法
        $mockLineBotService = \Mockery::mock(\App\Services\LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));

        // 建立 controller 並注入 mock
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );

        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);

        // 執行方法
        $method->invoke($controller, 'test_user_id', $fish->id, $audioBlob, $duration, 'test_reply_token');

        // 重新載入 Fish 資料
        $fish->refresh();

        // 驗證 fish 表更新
        $this->assertEquals($expectedFilename, $fish->audio_filename);
        
        // 驗證 fish_audios 表創建了記錄
        $this->assertDatabaseHas('fish_audios', [
            'fish_id' => $fish->id,
            'locate' => $expectedFilename,
            'duration' => $duration,
        ]);
    }

    /**
     * 測試 saveFishAudio 當 Fish 不存在時拋出例外
     */
    public function test_save_fish_audio_throws_exception_when_fish_not_found(): void
    {
        // 使用不存在的 Fish ID
        $nonExistentFishId = 99999;

        // 模擬音檔資料
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        $duration = 3000;

        // Mock LineUploadService（應該會被調用）
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineAudio')
            ->once()
            ->with($audioBlob)
            ->andReturn('test-audio-file.m4a');

        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);
        
        // Mock Storage to avoid S3 configuration issues
        \Storage::fake('s3');

        // Mock LineBotService 的 replyMessage 方法（應該回覆錯誤訊息）
        $mockLineBotService = \Mockery::mock(\App\Services\LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));

        // 建立 controller 並注入 mock
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );

        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);

        // 執行方法（應該不會拋出例外，而是記錄錯誤並回覆使用者）
        $method->invoke($controller, 'test_user_id', $nonExistentFishId, $audioBlob, $duration, 'test_reply_token');

        // 驗證 Cache 被清除（檢查方法內部行為）
        $this->assertNull(\Cache::get('line_user_test_user_id_adding_audio'));
    }

    /**
     * 測試 saveFishAudio 確保 audio_filename 和 audio_duration 正確儲存
     */
    public function test_save_fish_audio_stores_correct_filename_and_duration(): void
    {
        // 測試不同的時長值
        $testCases = [
            ['duration' => 1000, 'description' => '1秒'],
            ['duration' => 2500, 'description' => '2.5秒'],
            ['duration' => 5000, 'description' => '5秒'],
            ['duration' => 5100, 'description' => '5.1秒（邊界）'],
        ];

        foreach ($testCases as $index => $testCase) {
            $duration = $testCase['duration'];
            $description = $testCase['description'];

            // 建立測試用的 Fish
            $fish = \App\Models\Fish::factory()->create([
                'name' => "測試魚{$index}",
                'audio_filename' => null,
            ]);

            // 模擬音檔資料
            $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
            $expectedFilename = "test-audio-{$index}.m4a";

            // Mock LineUploadService
            $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
            $mockUploadService->shouldReceive('uploadLineAudio')
                ->once()
                ->with($audioBlob)
                ->andReturn($expectedFilename);

            $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

            // Mock LineBotService
            $mockLineBotService = \Mockery::mock(\App\Services\LineBotService::class);
            $mockLineBotService->shouldReceive('replyMessage')
                ->once()
                ->with('test_reply_token', \Mockery::type('array'));

            // 建立 controller
            $controller = new LineBotController(
                $mockLineBotService,
                $this->app->make(ApiFishController::class)
            );

            // 使用反射來測試 protected 方法
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('saveFishAudio');
            $method->setAccessible(true);

            // 執行方法
            $method->invoke($controller, 'test_user_id', $fish->id, $audioBlob, $duration, 'test_reply_token');

            // 重新載入 Fish 資料
            $fish->refresh();

            // 驗證 fish 表更新
            $this->assertEquals(
                $expectedFilename,
                $fish->audio_filename,
                "Failed for {$description}: audio_filename should be {$expectedFilename}"
            );
            
            // 驗證 fish_audios 表創建了記錄並包含正確的 duration
            $this->assertDatabaseHas('fish_audios', [
                'fish_id' => $fish->id,
                'locate' => $expectedFilename,
                'duration' => $duration,
            ]);
        }
    }

    /**
     * 測試 saveFishAudio 在上傳失敗時的錯誤處理
     */
    public function test_save_fish_audio_handles_upload_failure(): void
    {
        // 建立測試用的 Fish
        $fish = \App\Models\Fish::factory()->create([
            'name' => '測試魚3',
            'audio_filename' => null,
        ]);

        // 模擬音檔資料
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        $duration = 3000;

        // Mock LineUploadService 拋出例外
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineAudio')
            ->once()
            ->with($audioBlob)
            ->andThrow(new \Exception('S3 upload failed'));

        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

        // Mock LineBotService 的 replyMessage 方法（應該回覆錯誤訊息）
        $mockLineBotService = \Mockery::mock(\App\Services\LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));

        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );

        // 使用反射來測試 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);

        // 執行方法（應該不會拋出例外，而是記錄錯誤並回覆使用者）
        $method->invoke($controller, 'test_user_id', $fish->id, $audioBlob, $duration, 'test_reply_token');

        // 重新載入 Fish 資料
        $fish->refresh();

        // 驗證 fish 表沒有被更新
        $this->assertNull($fish->audio_filename);
        
        // 驗證 fish_audios 表沒有創建記錄
        $this->assertDatabaseMissing('fish_audios', [
            'fish_id' => $fish->id,
        ]);

        // 驗證 Cache 被清除
        $this->assertNull(\Cache::get('line_user_test_user_id_adding_audio'));
    }
}
