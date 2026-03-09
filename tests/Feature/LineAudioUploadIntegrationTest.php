<?php

/**
 * LINE Audio Upload Integration Test
 *
 * 完整的 LINE webhook 音檔上傳整合測試
 * 測試從接收訊息到儲存音檔的完整流程
 *
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
 */

use App\Http\Controllers\LineBotController;
use App\Services\LineBotService;
use App\Services\LineUploadService;
use App\Http\Controllers\ApiFishController;
use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LineAudioUploadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 設定測試環境變數
        config(['line.channel_secret' => 'test_channel_secret']);
        config(['line.channel_access_token' => 'test_access_token']);
        
        // 使用 fake S3
        Storage::fake('s3');
    }

    /**
     * 建立有效的 M4A 音檔測試資料
     */
    protected function createValidM4aAudioBlob(int $size = 1000): string
    {
        // M4A 檔案的典型開頭結構
        $header = pack('N', 32) . 'ftypisom' . str_repeat("\x00", 16);
        $audioData = str_repeat('A', max(0, $size - strlen($header)));
        
        return $header . $audioData;
    }

    /**
     * 建立有效的 LINE webhook 簽章
     */
    protected function createValidSignature(string $body): string
    {
        $channelSecret = config('line.channel_secret');
        return base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
    }

    /**
     * 建立 LINE webhook 音檔訊息事件的 JSON payload
     */
    protected function createAudioMessageWebhookPayload(
        string $userId,
        string $messageId,
        int $duration,
        string $replyToken
    ): string {
        $payload = [
            'destination' => 'test_destination',
            'events' => [
                [
                    'type' => 'message',
                    'message' => [
                        'type' => 'audio',
                        'id' => $messageId,
                        'duration' => $duration,
                    ],
                    'timestamp' => time() * 1000,
                    'source' => [
                        'type' => 'user',
                        'userId' => $userId,
                    ],
                    'replyToken' => $replyToken,
                    'mode' => 'active',
                ],
            ],
        ];
        
        return json_encode($payload);
    }

    /**
     * 測試完整的音檔上傳流程：從接收 webhook 到儲存音檔
     *
     * 驗證 Requirements: 1.1, 1.2, 1.3, 1.4
     */
    public function test_complete_audio_upload_flow(): void
    {
        Log::spy();
        
        // 1. 準備測試資料
        $fish = Fish::factory()->create([
            'name' => '測試魚類',
            'audio_filename' => null,
        ]);
        
        $userId = 'U1234567890abcdef';
        $messageId = 'test_message_id_12345';
        $duration = 3000; // 3 秒
        $replyToken = 'test_reply_token_xyz';
        
        // 設定使用者狀態（模擬使用者已點擊「新增發音」）
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // 2. 建立有效的音檔資料
        $audioBlob = $this->createValidM4aAudioBlob(5000);
        
        // 3. Mock LineBotService 的 getMessageContent 方法
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('getMessageContent')
            ->once()
            ->with($messageId)
            ->andReturn($audioBlob);
        
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with($replyToken, \Mockery::on(function ($messages) {
                // 驗證回覆訊息包含成功訊息
                return count($messages) === 1
                    && $messages[0] instanceof \LINE\Clients\MessagingApi\Model\TextMessage
                    && str_contains($messages[0]->getText(), '✅')
                    && str_contains($messages[0]->getText(), '成功');
            }));
        
        // 4. 建立 controller 並注入 mock
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 5. 建立 mock event
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        $mockMessage->shouldReceive('getDuration')->andReturn($duration);
        
        // 6. 執行音檔處理流程
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $mockEvent, $replyToken);
        
        // 7. 驗證結果
        
        // 7.1 驗證 Fish 記錄已更新
        $fish->refresh();
        $this->assertNotNull($fish->audio_filename);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/',
            $fish->audio_filename
        );
        
        // 7.2 驗證 FishAudio 記錄已創建（locate 改存部落名，name 才是 UUID 檔名）
        $this->assertDatabaseHas('fish_audios', [
            'fish_id'  => $fish->id,
            'name'     => $fish->audio_filename,
            'duration' => $duration,
        ]);
        
        // 7.3 驗證音檔已上傳到 S3
        $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        $audioPath = $audioFolder . '/' . $fish->audio_filename;
        $this->assertTrue(Storage::disk('s3')->exists($audioPath));
        
        // 7.4 驗證上傳的音檔內容完整性
        $uploadedContent = Storage::disk('s3')->get($audioPath);
        $this->assertEquals(strlen($audioBlob), strlen($uploadedContent));
        $this->assertEquals($audioBlob, $uploadedContent);
        
        // 7.5 驗證使用者狀態已清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
        
        // 7.6 驗證日誌記錄
        Log::shouldHaveReceived('info')
            ->with('LINE Bot audio saved successfully', \Mockery::on(function ($context) use ($userId, $duration) {
                return $context['userId'] === $userId
                    && $context['duration'] === $duration
                    && isset($context['filename']);
            }));
    }

    /**
     * 測試音檔可以正常播放（驗證 Content-Type 和格式）
     *
     * 驗證 Requirements: 1.5
     */
    public function test_uploaded_audio_is_playable(): void
    {
        Log::spy();
        
        // 1. 準備測試資料
        $fish = Fish::factory()->create([
            'name' => '測試魚類2',
            'audio_filename' => null,
        ]);
        
        $userId = 'U9876543210fedcba';
        $duration = 4500; // 4.5 秒
        
        // 建立真實的 M4A 格式音檔（包含正確的 magic bytes）
        $audioBlob = $this->createValidM4aAudioBlob(8000);
        
        // 2. 直接調用 saveFishAudio 方法
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')->once();
        
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);
        $method->invoke($controller, $userId, $fish->id, $audioBlob, $duration, 'test_token');
        
        // 3. 驗證音檔格式
        $fish->refresh();
        $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        $audioPath = $audioFolder . '/' . $fish->audio_filename;
        
        // 3.1 驗證檔案存在
        $this->assertTrue(Storage::disk('s3')->exists($audioPath));
        
        // 3.2 驗證音檔包含 M4A 格式標記
        $uploadedContent = Storage::disk('s3')->get($audioPath);
        $this->assertStringContainsString('ftyp', $uploadedContent);
        $this->assertStringContainsString('isom', $uploadedContent);
        
        // 3.3 驗證音檔大小正確
        $this->assertEquals(strlen($audioBlob), strlen($uploadedContent));
        
        // 3.4 驗證 audio_url 可以生成
        $audioUrl = $fish->audio_url;
        $this->assertNotNull($audioUrl);
        $this->assertStringContainsString($fish->audio_filename, $audioUrl);
    }

    /**
     * 測試不同時長的音檔上傳
     *
     * 驗證 Requirements: 1.1, 4.1, 4.3, 4.4
     */
    public function test_audio_upload_with_different_durations(): void
    {
        Log::spy();
        
        $testCases = [
            ['duration' => 1000, 'description' => '1秒', 'shouldAccept' => true],
            ['duration' => 2500, 'description' => '2.5秒', 'shouldAccept' => true],
            ['duration' => 5000, 'description' => '5秒（邊界）', 'shouldAccept' => true],
            ['duration' => 5100, 'description' => '5.1秒（最大容差）', 'shouldAccept' => true],
            ['duration' => 5101, 'description' => '5.101秒（超過限制）', 'shouldAccept' => false],
        ];
        
        foreach ($testCases as $index => $testCase) {
            $duration = $testCase['duration'];
            $description = $testCase['description'];
            $shouldAccept = $testCase['shouldAccept'];
            
            // 建立測試魚類
            $fish = Fish::factory()->create([
                'name' => "測試魚{$index}",
                'audio_filename' => null,
            ]);
            
            $userId = "U{$index}1234567890";
            $messageId = "msg_{$index}_12345";
            $replyToken = "token_{$index}";
            
            // 設定使用者狀態
            Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
            
            // 建立音檔資料
            $audioBlob = $this->createValidM4aAudioBlob(3000);
            
            // Mock LineBotService
            $mockLineBotService = \Mockery::mock(LineBotService::class);
            
            if ($shouldAccept) {
                $mockLineBotService->shouldReceive('getMessageContent')
                    ->once()
                    ->with($messageId)
                    ->andReturn($audioBlob);
                
                $mockLineBotService->shouldReceive('replyMessage')
                    ->once()
                    ->with($replyToken, \Mockery::on(function ($messages) {
                        return str_contains($messages[0]->getText(), '✅');
                    }));
            } else {
                // 時長超過限制，不應該下載音檔
                $mockLineBotService->shouldReceive('replyMessage')
                    ->once()
                    ->with($replyToken, \Mockery::on(function ($messages) {
                        return str_contains($messages[0]->getText(), '❌')
                            && str_contains($messages[0]->getText(), '超過');
                    }));
            }
            
            // 建立 controller
            $controller = new LineBotController(
                $mockLineBotService,
                $this->app->make(ApiFishController::class)
            );
            
            // 建立 mock event
            $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
            $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
            $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
            
            $mockSource->shouldReceive('getUserId')->andReturn($userId);
            $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
            $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
            $mockMessage->shouldReceive('getId')->andReturn($messageId);
            $mockMessage->shouldReceive('getDuration')->andReturn($duration);
            
            // 執行處理
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('handleAudioMessage');
            $method->setAccessible(true);
            $method->invoke($controller, $mockEvent, $replyToken);
            
            // 驗證結果
            $fish->refresh();
            
            if ($shouldAccept) {
                // 應該接受：驗證音檔已儲存
                $this->assertNotNull(
                    $fish->audio_filename,
                    "Failed for {$description}: audio should be saved"
                );
                
                $this->assertDatabaseHas('fish_audios', [
                    'fish_id' => $fish->id,
                    'duration' => $duration,
                ]);
            } else {
                // 應該拒絕：驗證音檔未儲存
                $this->assertNull(
                    $fish->audio_filename,
                    "Failed for {$description}: audio should not be saved"
                );
                
                $this->assertDatabaseMissing('fish_audios', [
                    'fish_id' => $fish->id,
                ]);
            }
            
            // 驗證使用者狀態：成功時清除，失敗（超時）時保留供重試
            if ($shouldAccept) {
                $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"), "Success: cache should be cleared");
            } else {
                $this->assertNotNull(Cache::get("line_user_{$userId}_adding_audio"), "Failure: cache should be kept for retry");
            }
        }
    }

    /**
     * 測試音檔驗證失敗的情況
     *
     * 驗證 Requirements: 1.1, 1.2
     */
    public function test_audio_validation_failure(): void
    {
        Log::spy();
        
        // 建立測試魚類
        $fish = Fish::factory()->create([
            'name' => '測試魚類驗證',
            'audio_filename' => null,
        ]);
        
        $userId = 'U_validation_test';
        $messageId = 'msg_validation';
        $replyToken = 'token_validation';
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // 建立無效的音檔資料（太小）
        $invalidAudioBlob = str_repeat('x', 50); // 只有 50 bytes
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('getMessageContent')
            ->once()
            ->with($messageId)
            ->andReturn($invalidAudioBlob);
        
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with($replyToken, \Mockery::on(function ($messages) {
                return str_contains($messages[0]->getText(), '❌')
                    && str_contains($messages[0]->getText(), '格式');
            }));
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 建立 mock event
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        $mockMessage->shouldReceive('getDuration')->andReturn(3000);
        
        // 執行處理
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $mockEvent, $replyToken);
        
        // 驗證結果
        $fish->refresh();
        
        // 驗證音檔未儲存
        $this->assertNull($fish->audio_filename);
        $this->assertDatabaseMissing('fish_audios', [
            'fish_id' => $fish->id,
        ]);
        
        // 驗證使用者狀態已清除
        $this->assertNull(Cache::get("line_user_{$userId}_adding_audio"));
        
        // 驗證警告日誌
        Log::shouldHaveReceived('warning')
            ->with('LINE Bot audio validation failed', \Mockery::type('array'));
    }

    /**
     * 測試沒有使用者狀態時收到音檔的情況
     *
     * 驗證 Requirements: 1.1
     */
    public function test_audio_received_without_user_state(): void
    {
        Log::spy();
        
        $userId = 'U_no_state';
        $messageId = 'msg_no_state';
        $replyToken = 'token_no_state';
        
        // 不設定使用者狀態（模擬使用者未點擊「新增發音」就直接發送音檔）
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->with($replyToken, \Mockery::on(function ($messages) {
                return str_contains($messages[0]->getText(), '請先點擊');
            }));
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 建立 mock event
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        
        // 執行處理
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $mockEvent, $replyToken);
        
        // 驗證警告日誌
        Log::shouldHaveReceived('warning')
            ->with('LINE Bot audio received without active state', \Mockery::type('array'));
    }

    /**
     * 測試音檔上傳後可以正確生成 URL
     *
     * 驗證 Requirements: 1.4
     */
    public function test_audio_url_generation_after_upload(): void
    {
        Log::spy();
        
        // 建立測試魚類
        $fish = Fish::factory()->create([
            'name' => '測試URL生成',
            'audio_filename' => null,
        ]);
        
        $userId = 'U_url_test';
        $duration = 3500;
        $audioBlob = $this->createValidM4aAudioBlob(4000);
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')->once();
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 執行儲存
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);
        $method->invoke($controller, $userId, $fish->id, $audioBlob, $duration, 'test_token');
        
        // 驗證 URL 生成
        $fish->refresh();
        
        // 驗證 audio_url 屬性存在
        $this->assertNotNull($fish->audio_url);
        
        // 驗證 URL 包含檔案名稱
        $this->assertStringContainsString($fish->audio_filename, $fish->audio_url);
        
        // 驗證 URL 包含音檔資料夾路徑
        $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        $this->assertStringContainsString($audioFolder, $fish->audio_url);
        
        // 驗證檔案確實存在於 S3
        $audioPath = $audioFolder . '/' . $fish->audio_filename;
        $this->assertTrue(Storage::disk('s3')->exists($audioPath));
    }

    /**
     * 測試音檔上傳的完整日誌記錄
     *
     * 驗證 Requirements: 3.1, 3.2, 3.4
     */
    public function test_complete_logging_during_upload(): void
    {
        Log::spy();
        
        // 建立測試魚類
        $fish = Fish::factory()->create([
            'name' => '測試日誌',
            'audio_filename' => null,
        ]);
        
        $userId = 'U_logging_test';
        $messageId = 'msg_logging';
        $duration = 4000;
        $replyToken = 'token_logging';
        
        // 設定使用者狀態
        Cache::put("line_user_{$userId}_adding_audio", $fish->id, now()->addMinutes(5));
        
        // 建立音檔資料
        $audioBlob = $this->createValidM4aAudioBlob(6000);
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('getMessageContent')
            ->once()
            ->with($messageId)
            ->andReturn($audioBlob);
        
        $mockLineBotService->shouldReceive('replyMessage')->once();
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 建立 mock event
        $mockEvent = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $mockSource = \Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $mockMessage = \Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        
        $mockSource->shouldReceive('getUserId')->andReturn($userId);
        $mockEvent->shouldReceive('getSource')->andReturn($mockSource);
        $mockEvent->shouldReceive('getMessage')->andReturn($mockMessage);
        $mockMessage->shouldReceive('getId')->andReturn($messageId);
        $mockMessage->shouldReceive('getDuration')->andReturn($duration);
        
        // 執行處理
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('handleAudioMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $mockEvent, $replyToken);
        
        // 驗證各階段的日誌記錄
        
        // 1. 接收音檔訊息
        Log::shouldHaveReceived('info')
            ->with('LINE Bot received audio message', \Mockery::on(function ($context) use ($userId, $messageId) {
                return $context['userId'] === $userId
                    && $context['messageId'] === $messageId;
            }));
        
        // 2. 時長檢查
        Log::shouldHaveReceived('info')
            ->with('LINE Bot audio duration check', \Mockery::on(function ($context) use ($userId, $duration) {
                return $context['userId'] === $userId
                    && $context['duration'] === $duration;
            }));
        
        // 3. 下載音檔
        Log::shouldHaveReceived('info')
            ->with('LINE Bot downloading audio content', \Mockery::on(function ($context) use ($userId, $messageId) {
                return $context['userId'] === $userId
                    && $context['messageId'] === $messageId;
            }));
        
        // 4. 音檔詳細資訊（包含前 16 bytes）
        Log::shouldHaveReceived('info')
            ->with('LINE Bot audio details', \Mockery::on(function ($context) use ($userId, $duration) {
                return $context['userId'] === $userId
                    && $context['duration'] === $duration
                    && isset($context['size'])
                    && isset($context['first_bytes']);
            }));
        
        // 5. 儲存成功
        Log::shouldHaveReceived('info')
            ->with('LINE Bot audio saved successfully', \Mockery::on(function ($context) use ($userId, $duration) {
                return $context['userId'] === $userId
                    && $context['duration'] === $duration
                    && isset($context['filename']);
            }));
    }

    /**
     * 測試音檔覆蓋上傳（使用者重新錄製）
     *
     * 驗證 Requirements: 1.2, 1.3
     */
    public function test_audio_overwrite_on_reupload(): void
    {
        Log::spy();
        
        // 建立已有音檔的魚類
        $fish = Fish::factory()->create([
            'name' => '測試覆蓋',
            'audio_filename' => 'old-audio-file.m4a',
        ]);
        
        // 建立舊的 FishAudio 記錄（使用較早的時間戳記）
        $oldAudio = FishAudio::create([
            'fish_id'  => $fish->id,
            'name'     => $fish->audio_filename, // name 才是 UUID 檔名
            'locate'   => 'iraraley',            // locate 存部落名
            'duration' => 2000,
        ]);
        
        // 確保舊記錄的時間戳記較早
        $oldAudio->created_at = now()->subMinutes(10);
        $oldAudio->save();
        
        $userId = 'U_overwrite_test';
        $duration = 3500;
        $audioBlob = $this->createValidM4aAudioBlob(5000);
        
        // Mock LineBotService
        $mockLineBotService = \Mockery::mock(LineBotService::class);
        $mockLineBotService->shouldReceive('replyMessage')->once();
        
        // 建立 controller
        $controller = new LineBotController(
            $mockLineBotService,
            $this->app->make(ApiFishController::class)
        );
        
        // 執行儲存
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('saveFishAudio');
        $method->setAccessible(true);
        $method->invoke($controller, $userId, $fish->id, $audioBlob, $duration, 'test_token');
        
        // 驗證結果
        $fish->refresh();
        
        // 驗證 audio_filename 已更新為新檔案
        $this->assertNotEquals('old-audio-file.m4a', $fish->audio_filename);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/',
            $fish->audio_filename
        );
        
        // 驗證有兩筆 FishAudio 記錄（舊的和新的）
        $audioCount = FishAudio::where('fish_id', $fish->id)->count();
        $this->assertEquals(2, $audioCount, '應該有兩筆音檔記錄（舊的和新的）');
        
        // 驗證新的 FishAudio 記錄已創建
        $latestAudio = FishAudio::where('fish_id', $fish->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        // 驗證最新 FishAudio 記錄的 name（UUID 檔名）與 duration 正確
        $this->assertEquals($fish->audio_filename, $latestAudio->name);
        $this->assertEquals($duration, $latestAudio->duration);
        $this->assertNotEquals($oldAudio->id, $latestAudio->id, '最新的記錄應該是新創建的');
        
        // 驗證新音檔已上傳
        $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        $newAudioPath = $audioFolder . '/' . $fish->audio_filename;
        $this->assertTrue(Storage::disk('s3')->exists($newAudioPath));
    }

    /**
     * 測試多個使用者同時上傳音檔（並發情況）
     *
     * 驗證 Requirements: 1.1, 1.2, 1.3
     */
    public function test_concurrent_audio_uploads_from_multiple_users(): void
    {
        Log::spy();
        
        $userCount = 3;
        $fishes = [];
        $userIds = [];
        
        // 建立多個測試魚類和使用者
        for ($i = 0; $i < $userCount; $i++) {
            $fish = Fish::factory()->create([
                'name' => "並發測試魚{$i}",
                'audio_filename' => null,
            ]);
            $fishes[] = $fish;
            $userIds[] = "U_concurrent_{$i}";
            
            // 設定使用者狀態
            Cache::put("line_user_{$userIds[$i]}_adding_audio", $fish->id, now()->addMinutes(5));
        }
        
        // 為每個使用者執行上傳
        for ($i = 0; $i < $userCount; $i++) {
            $audioBlob = $this->createValidM4aAudioBlob(2000 + ($i * 1000));
            $duration = 2000 + ($i * 500);
            
            // Mock LineBotService
            $mockLineBotService = \Mockery::mock(LineBotService::class);
            $mockLineBotService->shouldReceive('replyMessage')->once();
            
            // 建立 controller
            $controller = new LineBotController(
                $mockLineBotService,
                $this->app->make(ApiFishController::class)
            );
            
            // 執行儲存
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('saveFishAudio');
            $method->setAccessible(true);
            $method->invoke($controller, $userIds[$i], $fishes[$i]->id, $audioBlob, $duration, "token_{$i}");
        }
        
        // 驗證所有音檔都已正確儲存
        for ($i = 0; $i < $userCount; $i++) {
            $fishes[$i]->refresh();
            
            // 驗證 audio_filename 已設定
            $this->assertNotNull($fishes[$i]->audio_filename);
            
            // 驗證 FishAudio 記錄存在（name 欄位才是 UUID 檔名）
            $this->assertDatabaseHas('fish_audios', [
                'fish_id' => $fishes[$i]->id,
                'name'    => $fishes[$i]->audio_filename,
            ]);
            
            // 驗證音檔已上傳
            $audioFolder = app(\App\Contracts\StorageServiceInterface::class)->getAudioFolder();
            $audioPath = $audioFolder . '/' . $fishes[$i]->audio_filename;
            $this->assertTrue(Storage::disk('s3')->exists($audioPath));
            
            // 驗證使用者狀態已清除
            $this->assertNull(Cache::get("line_user_{$userIds[$i]}_adding_audio"));
        }
        
        // 驗證所有檔案名稱都是唯一的
        $filenames = array_map(fn ($fish) => $fish->audio_filename, $fishes);
        $uniqueFilenames = array_unique($filenames);
        $this->assertCount($userCount, $uniqueFilenames, '所有音檔檔名應該是唯一的');
    }
}
