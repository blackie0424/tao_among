<?php

use App\Http\Controllers\LineBotController;
use App\Services\LineBotService;
use App\Services\LineUploadService;
use App\Http\Controllers\ApiFishController;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 測試 LINE 音檔上傳的錯誤處理
 *
 * 驗證 Requirements 2.3, 3.3:
 * - 所有例外都有詳細的錯誤訊息
 * - 所有錯誤都有完整的堆疊追蹤記錄
 * - 測試各種錯誤情況（下載失敗、上傳失敗、資料庫失敗）
 * - 確保使用者狀態在錯誤時被正確清除
 */
class LineAudioUploadErrorHandlingTest extends TestCase
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
     * 測試下載失敗時的錯誤處理
     * 驗證：錯誤日誌記錄、使用者狀態清除、錯誤訊息回覆
     */
    public function test_download_failure_error_handling(): void
    {
        Log::spy();
        
        $fish = Fish::factory()->create(['name' => '測試魚']);
        $userId = 'test_user_id';
        $messageId = 'test_message_id';
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // Mock LineBotService 讓 getMessageContent 拋出例外
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('getMessageContent')
            ->once()
            ->with($messageId)
            ->andThrow(new \Exception('Failed to download from LINE API'));
        
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));
        
        // 建立 mock event
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        $mockMessage->shouldReceive('getDuration')->andReturn(3000);
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 使用反射調用 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        
        // 執行方法
        $method->invoke($controller, $mockEvent, 'test_reply_token');
        
        // 驗證錯誤日誌被記錄
        Log::shouldHaveReceived('error')
            ->once()
            ->with('LINE Bot failed to download audio from LINE API', \Mockery::on(function ($context) use ($userId, $messageId) {
                return $context['userId'] === $userId
                    && $context['messageId'] === $messageId
                    && isset($context['error'])
                    && isset($context['trace'])
                    && isset($context['exception_class']);
            }));
        
        // 驗證使用者狀態被清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
    }

    /**
     * 測試上傳失敗時的錯誤處理
     * 驗證：錯誤日誌記錄、使用者狀態清除、錯誤訊息回覆
     */
    public function test_upload_failure_error_handling(): void
    {
        Log::spy();
        
        $fish = Fish::factory()->create(['name' => '測試魚']);
        $userId = 'test_user_id';
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        $duration = 3000;
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // Mock LineUploadService 拋出例外
        $mockUploadService = \Mockery::mock(LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineAudio')
            ->once()
            ->with($audioBlob)
            ->andThrow(new \Exception('S3 upload failed'));
        
        $this->app->instance(LineUploadService::class, $mockUploadService);
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 使用反射調用 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);
        
        // 執行方法
        $method->invoke($controller, $userId, $fish->id, $audioBlob, $duration, 'test_reply_token');
        
        // 驗證錯誤日誌被記錄
        Log::shouldHaveReceived('error')
            ->with('LINE Bot failed to upload audio to S3', \Mockery::on(function ($context) use ($userId) {
                return $context['userId'] === $userId
                    && isset($context['error'])
                    && isset($context['trace'])
                    && isset($context['exception_class']);
            }));
        
        // 驗證使用者狀態被清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
        
        // 驗證資料庫沒有被更新
        $fish->refresh();
        $this->assertNull($fish->audio_filename);
    }

    /**
     * 測試資料庫更新失敗時的錯誤處理
     * 驗證：錯誤日誌記錄、使用者狀態清除、孤兒檔案清理
     */
    public function test_database_failure_error_handling(): void
    {
        Log::spy();
        Storage::fake('s3');
        
        $userId = 'test_user_id';
        $nonExistentFishId = 99999;
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        $duration = 3000;
        $uploadedFilename = 'test-audio.m4a';
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $nonExistentFishId, now()->addMinutes(5));
        
        // Mock LineUploadService 成功上傳
        $mockUploadService = \Mockery::mock(LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineAudio')
            ->once()
            ->with($audioBlob)
            ->andReturn($uploadedFilename);
        
        $this->app->instance(LineUploadService::class, $mockUploadService);
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 使用反射調用 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);
        
        // 執行方法
        $method->invoke($controller, $userId, $nonExistentFishId, $audioBlob, $duration, 'test_reply_token');
        
        // 驗證錯誤日誌被記錄（Fish not found）
        Log::shouldHaveReceived('error')
            ->with('LINE Bot fish not found when saving audio', \Mockery::on(function ($context) use ($userId, $nonExistentFishId) {
                return $context['userId'] === $userId
                    && $context['fishId'] === $nonExistentFishId;
            }));
        
        // 驗證使用者狀態被清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
    }

    /**
     * 測試時長超過限制時的錯誤處理
     * 驗證：使用者狀態清除、警告日誌記錄
     */
    public function test_duration_exceeded_error_handling(): void
    {
        Log::spy();
        
        $fish = Fish::factory()->create(['name' => '測試魚']);
        $userId = 'test_user_id';
        $messageId = 'test_message_id';
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with('test_reply_token', \Mockery::type('array'));
        
        // 建立 mock event（時長超過 5100ms）
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        $mockMessage->shouldReceive('getDuration')->andReturn(5101); // 超過限制
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 使用反射調用 protected 方法
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        
        // 執行方法
        $method->invoke($controller, $mockEvent, 'test_reply_token');
        
        // 驗證警告日誌被記錄
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('LINE Bot audio duration exceeded', \Mockery::on(function ($context) use ($userId) {
                return $context['userId'] === $userId
                    && $context['duration'] === 5101
                    && $context['max_allowed'] === 5100;
            }));
        
        // 驗證使用者狀態被清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
    }

    /**
     * 測試 LineUploadService 的錯誤處理
     * 驗證：詳細的錯誤訊息、堆疊追蹤記錄
     */
    public function test_line_upload_service_error_logging(): void
    {
        Log::spy();
        Storage::fake('s3');
        
        // 強制 Storage::put 失敗
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        
        Storage::shouldReceive('put')
            ->andReturn(false);
        
        $storageService = $this->app->make(\App\Contracts\StorageServiceInterface::class);
        $lineUploadService = new LineUploadService($storageService);
        
        $audioBlob = str_repeat("\x00", 4) . 'ftyp' . 'M4A ' . str_repeat("\x00", 100);
        
        try {
            $lineUploadService->uploadLineAudio($audioBlob);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // 驗證例外訊息包含詳細資訊
            $this->assertStringContainsString('Failed to upload LINE audio to S3', $e->getMessage());
            
            // 驗證錯誤日誌被記錄
            Log::shouldHaveReceived('error')
                ->once()
                ->with('LINE Upload: Failed to upload audio', \Mockery::on(function ($context) {
                    return isset($context['error'])
                        && isset($context['trace'])
                        && isset($context['exception_class'])
                        && isset($context['filename'])
                        && isset($context['path']);
                }));
        }
    }

    /**
     * 測試空音檔資料流的錯誤處理
     */
    public function test_empty_audio_stream_error_handling(): void
    {
        Log::spy();
        
        $storageService = $this->app->make(\App\Contracts\StorageServiceInterface::class);
        $lineUploadService = new LineUploadService($storageService);
        
        $emptyAudioBlob = '';
        
        try {
            $lineUploadService->uploadLineAudio($emptyAudioBlob);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // 驗證例外訊息
            $this->assertStringContainsString('Audio stream is empty', $e->getMessage());
            
            // 驗證錯誤日誌被記錄
            Log::shouldHaveReceived('error')
                ->once()
                ->with('LINE Upload: Failed to upload audio', \Mockery::type('array'));
        }
    }

    /**
     * 測試所有錯誤情況都清除使用者狀態
     */
    public function test_all_error_scenarios_clear_user_state(): void
    {
        $userId = 'test_user_id';
        $fish = Fish::factory()->create(['name' => '測試魚']);
        
        $errorScenarios = [
            'download_failure' => function () use ($userId, $fish) {
                Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
                
                $mockLineBotService = \Mockery::mock(LineBotService::class);
                $mockLineBotService->shouldReceive('getMessageContent')->andThrow(new \Exception('Download failed'));
                $mockLineBotService->shouldReceive('replyMessage');
                
                $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
                $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
                $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
                
                $mockSource->shouldReceive('getUserId')->andReturn($userId);
                $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
                $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
                $mockMessage->shouldReceive('getId')->andReturn('msg_id');
                $mockMessage->shouldReceive('getDuration')->andReturn(3000);
                
                $controller = new LineBotController($mockLineBotService, $this->app->make(ApiFishController::class));
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('handleAudioMessage');
                $method->setAccessible(true);
                $method->invoke($controller, $mockEvent, 'token');
            },
            'upload_failure' => function () use ($userId, $fish) {
                Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
                
                $mockUploadService = \Mockery::mock(LineUploadService::class);
                $mockUploadService->shouldReceive('uploadLineAudio')->andThrow(new \Exception('Upload failed'));
                $this->app->instance(LineUploadService::class, $mockUploadService);
                
                $mockLineBotService = \Mockery::mock(LineBotService::class);
                $mockLineBotService->shouldReceive('replyMessage');
                
                $controller = new LineBotController($mockLineBotService, $this->app->make(ApiFishController::class));
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('saveFishAudio');
                $method->setAccessible(true);
                $method->invoke($controller, $userId, $fish->id, 'blob', 3000, 'token');
            },
            'duration_exceeded' => function () use ($userId, $fish) {
                Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
                
                $mockLineBotService = \Mockery::mock(LineBotService::class);
                $mockLineBotService->shouldReceive('replyMessage');
                
                $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
                $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
                $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
                
                $mockSource->shouldReceive('getUserId')->andReturn($userId);
                $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
                $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
                $mockMessage->shouldReceive('getId')->andReturn('msg_id');
                $mockMessage->shouldReceive('getDuration')->andReturn(5101);
                
                $controller = new LineBotController($mockLineBotService, $this->app->make(ApiFishController::class));
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('handleAudioMessage');
                $method->setAccessible(true);
                $method->invoke($controller, $mockEvent, 'token');
            },
        ];
        
        foreach ($errorScenarios as $scenarioName => $scenario) {
            // 執行錯誤情境
            try {
                $scenario();
            } catch (\Exception $e) {
                // 忽略例外，我們只關心狀態清除
            }
            
            // 驗證使用者狀態被清除
            $this->assertNull(
                Cache::get("line_user_{$userId}_adding_audio"),
                "User state should be cleared in scenario: {$scenarioName}"
            );
        }
    }
}
