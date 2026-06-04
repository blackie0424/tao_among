<?php

use App\Http\Controllers\LineBotController;
use App\Http\Controllers\ApiFishController;
use App\Contracts\LineMessagingClientInterface;
use App\Services\UploadService;
use App\Contracts\StorageServiceInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\FishServiceInterface;
use App\Services\FishService;
use App\Models\Fish;
use App\Models\CaptureRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 測試「新增魚類」流程補上完整捕獲資訊
 *
 * 命名完成後不直接建立 Fish，改為收集捕獲紀錄資訊（部落、地點、捕獲方式、日期、備註），
 * 所有資料齊全後再以 transaction 一次建立 Fish + CaptureRecord。
 */
class LineFishCreateCaptureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected const USER_ID = 'test_user_capture_flow';
    protected const REPLY_TOKEN = 'test_reply_token_capture';

    protected LineBotController $controller;
    protected \Mockery\MockInterface $mockLineBotService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['line.channel_secret' => 'test_channel_secret']);
        config(['line.channel_access_token' => 'test_access_token']);
        config(['fish_options.tribes' => ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']]);
        config(['fish_options.capture_methods' => [
            'mapazat'  => 'mapazat 網魚',
            'mamasil'  => 'mamasil 白天釣魚',
            'mamacik'  => 'mamacik 夜間釣魚',
            'mitokzos' => 'mitokzos',
            'mipaltog' => 'mipaltog',
        ]]);

        Cache::flush();

        $this->mockLineBotService = \Mockery::mock(LineMessagingClientInterface::class);
        $this->mockLineBotService
            ->shouldReceive('getUserProfile')
            ->andReturn(['displayName' => 'Test User', 'pictureUrl' => null])
            ->byDefault();
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->byDefault();

        $mockLineUserService = \Mockery::mock(LineUserServiceInterface::class);
        $mockLineUserService->shouldReceive('upsert')->andReturn(new \App\Models\User())->byDefault();
        $mockLineUserService->shouldReceive('getRole')->andReturn('editor')->byDefault();

        $this->controller = new LineBotController(
            $this->mockLineBotService,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $mockLineUserService,
            $this->app->make(FishServiceInterface::class),
        );
    }

    // =====================================================
    // 命名完成後 → 轉換到 waiting_capture_tribe
    // =====================================================

    /** @test */
    public function test_default_name_transitions_to_waiting_capture_tribe_without_creating_fish(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_tribe', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertDatabaseCount('fish', 0);
    }

    /** @test */
    public function test_default_name_stores_name_in_cache(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('我不知道', Cache::get("line_user_" . self::USER_ID . "_create_fish_name"));
    }

    /** @test */
    public function test_custom_name_text_transitions_to_waiting_capture_tribe_without_creating_fish(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_custom_name', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('黑鰭燕魟'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_tribe', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertDatabaseCount('fish', 0);
    }

    /** @test */
    public function test_custom_name_text_stores_name_in_cache(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_custom_name', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('黑鰭燕魟'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('黑鰭燕魟', Cache::get("line_user_" . self::USER_ID . "_create_fish_name"));
    }

    // =====================================================
    // 部落選單 → Flex Message
    // =====================================================

    /** @test */
    public function test_tribe_selection_sends_flex_message_not_text_message(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $capturedMessages = [];
        $this->mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->withArgs(function ($token, $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            });

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertCount(1, $capturedMessages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $capturedMessages[0]);
        $this->assertStringContainsString('部落', $capturedMessages[0]->getAltText());
    }

    /** @test */
    public function test_tribe_selection_flex_contains_all_config_tribes(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $capturedMessages = [];
        $this->mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->withArgs(function ($token, $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            });

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $flex = $capturedMessages[0];
        $bodyJson = json_encode($flex->getContents());

        foreach (config('fish_options.tribes', []) as $tribe) {
            $this->assertStringContainsString("tribe={$tribe}", $bodyJson);
        }
    }

    // =====================================================
    // 捕獲方式選單 → Flex Message + config 選項
    // =====================================================

    /** @test */
    public function test_capture_method_selection_sends_flex_message_not_text_message(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_location', now()->addMinutes(10));

        $capturedMessages = [];
        $this->mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->withArgs(function ($token, $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            });

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('小蘭嶼南側礁石'),
            self::REPLY_TOKEN
        );

        $this->assertCount(1, $capturedMessages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $capturedMessages[0]);
        $this->assertStringContainsString('捕獲方式', $capturedMessages[0]->getAltText());
    }

    /** @test */
    public function test_capture_method_flex_contains_all_config_methods(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_location', now()->addMinutes(10));

        $capturedMessages = [];
        $this->mockLineBotService->shouldReceive('replyMessage')
            ->once()
            ->withArgs(function ($token, $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            });

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('小蘭嶼南側礁石'),
            self::REPLY_TOKEN
        );

        $flex = $capturedMessages[0];
        $bodyJson = json_encode($flex->getContents());

        foreach (array_keys(config('fish_options.capture_methods', [])) as $methodKey) {
            $this->assertStringContainsString("capture_method={$methodKey}", $bodyJson);
        }
    }

    // =====================================================
    // 部落選擇
    // =====================================================

    /** @test */
    public function test_select_tribe_transitions_to_waiting_capture_location(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_tribe', now()->addMinutes(10));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_create_fish_tribe&tribe=ivalino'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_location', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertEquals('ivalino', Cache::get("line_user_" . self::USER_ID . "_create_fish_tribe"));
    }

    // =====================================================
    // 地點輸入
    // =====================================================

    /** @test */
    public function test_location_text_transitions_to_waiting_capture_method(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_location', now()->addMinutes(10));

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('小蘭嶼南側礁石'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_method', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertEquals('小蘭嶼南側礁石', Cache::get("line_user_" . self::USER_ID . "_create_fish_location"));
    }

    // =====================================================
    // 捕獲方式選擇
    // =====================================================

    /** @test */
    public function test_select_method_transitions_to_waiting_capture_date(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_method', now()->addMinutes(10));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_create_fish_method&capture_method=mapazat'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_date', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertEquals('mapazat', Cache::get("line_user_" . self::USER_ID . "_create_fish_capture_method"));
    }

    // =====================================================
    // 日期選擇
    // =====================================================

    /** @test */
    public function test_select_date_today_stores_today_and_transitions_to_waiting_notes(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_date', now()->addMinutes(10));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_create_fish_date_today'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_notes', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertEquals(now()->toDateString(), Cache::get("line_user_" . self::USER_ID . "_create_fish_capture_date"));
    }

    /** @test */
    public function test_select_date_custom_transitions_to_waiting_capture_date_input(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_date', now()->addMinutes(10));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_create_fish_date_custom'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_date_input', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
    }

    /** @test */
    public function test_custom_date_text_stores_date_and_transitions_to_waiting_notes(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_date_input', now()->addMinutes(10));

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('2026-05-20'),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_capture_notes', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertEquals('2026-05-20', Cache::get("line_user_" . self::USER_ID . "_create_fish_capture_date"));
    }

    // =====================================================
    // 備註 → 建立 Fish + CaptureRecord
    // =====================================================

    /** @test */
    public function test_notes_text_creates_fish_and_capture_record_with_all_data(): void
    {
        $this->seedCaptureFlowCache(notes: null);
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_notes', now()->addMinutes(10));

        $this->mockLineBotService->shouldReceive('getUrl')->andReturn('https://example.com/img.jpg')->byDefault();

        $mockStorage = \Mockery::mock(StorageServiceInterface::class);
        $mockStorage->shouldReceive('getUrl')->andReturn('https://example.com/img.jpg');
        $this->app->instance(StorageServiceInterface::class, $mockStorage);

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('礁石旁發現，體型較大'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);
        $this->assertDatabaseCount('capture_records', 1);

        $fish = Fish::first();
        $this->assertEquals('黑鰭燕魟', $fish->name);

        $record = CaptureRecord::first();
        $this->assertEquals('ivalino', $record->tribe);
        $this->assertEquals('小蘭嶼南側礁石', $record->location);
        $this->assertEquals('mapazat', $record->capture_method);
        $this->assertEquals('2026-05-20', $record->capture_date->toDateString());
        $this->assertEquals('礁石旁發現，體型較大', $record->notes);
    }

    /** @test */
    public function test_skip_notes_creates_fish_and_capture_record_with_null_notes(): void
    {
        $this->seedCaptureFlowCache(notes: null);
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_notes', now()->addMinutes(10));

        $mockStorage = \Mockery::mock(StorageServiceInterface::class);
        $mockStorage->shouldReceive('getUrl')->andReturn('https://example.com/img.jpg');
        $this->app->instance(StorageServiceInterface::class, $mockStorage);

        $this->callHandlePostback(
            $this->makePostbackEvent('action=skip_create_fish_notes'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);
        $this->assertDatabaseCount('capture_records', 1);
        $this->assertNull(CaptureRecord::first()->notes);
    }

    /** @test */
    public function test_creating_fish_clears_all_capture_flow_cache_keys(): void
    {
        $this->seedCaptureFlowCache(notes: null);
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_notes', now()->addMinutes(10));

        $mockStorage = \Mockery::mock(StorageServiceInterface::class);
        $mockStorage->shouldReceive('getUrl')->andReturn('https://example.com/img.jpg');
        $this->app->instance(StorageServiceInterface::class, $mockStorage);

        $this->callHandlePostback(
            $this->makePostbackEvent('action=skip_create_fish_notes'),
            self::REPLY_TOKEN
        );

        $prefix = "line_user_" . self::USER_ID . "_create_fish_";
        $this->assertNull(Cache::get("{$prefix}state"));
        $this->assertNull(Cache::get("{$prefix}images"));
        $this->assertNull(Cache::get("{$prefix}name"));
        $this->assertNull(Cache::get("{$prefix}tribe"));
        $this->assertNull(Cache::get("{$prefix}location"));
        $this->assertNull(Cache::get("{$prefix}capture_method"));
        $this->assertNull(Cache::get("{$prefix}capture_date"));
        $this->assertNull(Cache::get("{$prefix}notes"));
    }

    // =====================================================
    // 取消流程 → 清除所有 Cache
    // =====================================================

    /** @test */
    public function test_cancel_during_capture_flow_clears_all_new_cache_keys(): void
    {
        $this->seedCaptureFlowCache(notes: null);
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_capture_tribe', now()->addMinutes(10));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=cancel_create_fish'),
            self::REPLY_TOKEN
        );

        $prefix = "line_user_" . self::USER_ID . "_create_fish_";
        $this->assertNull(Cache::get("{$prefix}state"));
        $this->assertNull(Cache::get("{$prefix}images"));
        $this->assertNull(Cache::get("{$prefix}name"));
        $this->assertNull(Cache::get("{$prefix}tribe"));
        $this->assertNull(Cache::get("{$prefix}location"));
        $this->assertNull(Cache::get("{$prefix}capture_method"));
        $this->assertNull(Cache::get("{$prefix}capture_date"));
    }

    // =====================================================
    // FishService: createFishFromLine 使用真實捕獲資料
    // =====================================================

    /** @test */
    public function test_fish_service_creates_fish_and_capture_record_with_real_capture_data(): void
    {
        $service = $this->app->make(FishService::class);

        $captureData = [
            'tribe'          => 'ivalino',
            'location'       => '小蘭嶼南側礁石',
            'capture_method' => 'mapazat',
            'capture_date'   => '2026-05-20',
            'notes'          => '體型較大',
        ];

        $fish = $service->createFishFromLine('黑鰭燕魟', ['img1.jpg', 'img2.jpg'], $captureData);

        $this->assertDatabaseCount('fish', 1);
        $this->assertDatabaseCount('capture_records', 2);

        $this->assertEquals('黑鰭燕魟', $fish->name);
        $this->assertEquals('img1.jpg', $fish->image);
        $this->assertNotNull($fish->display_capture_record_id);

        $record = CaptureRecord::first();
        $this->assertEquals('ivalino', $record->tribe);
        $this->assertEquals('小蘭嶼南側礁石', $record->location);
        $this->assertEquals('mapazat', $record->capture_method);
        $this->assertEquals('2026-05-20', $record->capture_date->toDateString());
        $this->assertEquals('體型較大', $record->notes);
    }

    /** @test */
    public function test_fish_service_each_image_creates_separate_capture_record(): void
    {
        $service = $this->app->make(FishService::class);

        $captureData = [
            'tribe'          => 'iraraley',
            'location'       => '海灣',
            'capture_method' => 'mamasil',
            'capture_date'   => '2026-06-01',
            'notes'          => null,
        ];

        $service->createFishFromLine('測試魚', ['img1.jpg', 'img2.jpg', 'img3.jpg'], $captureData);

        $this->assertDatabaseCount('capture_records', 3);
        CaptureRecord::all()->each(function ($record) {
            $this->assertEquals('iraraley', $record->tribe);
            $this->assertEquals('海灣', $record->location);
        });
    }

    // =====================================================
    // 輔助方法
    // =====================================================

    private function seedCaptureFlowCache(?string $notes): void
    {
        $prefix = "line_user_" . self::USER_ID . "_create_fish_";
        Cache::put("{$prefix}images", ['img1.jpg'], now()->addMinutes(10));
        Cache::put("{$prefix}name", '黑鰭燕魟', now()->addMinutes(10));
        Cache::put("{$prefix}tribe", 'ivalino', now()->addMinutes(10));
        Cache::put("{$prefix}location", '小蘭嶼南側礁石', now()->addMinutes(10));
        Cache::put("{$prefix}capture_method", 'mapazat', now()->addMinutes(10));
        Cache::put("{$prefix}capture_date", '2026-05-20', now()->addMinutes(10));
        if ($notes !== null) {
            Cache::put("{$prefix}notes", $notes, now()->addMinutes(10));
        }
    }

    private function makePostbackEvent(string $data): object
    {
        $source = new class(self::USER_ID) {
            public function __construct(private string $userId) {}
            public function getUserId(): string { return $this->userId; }
        };

        $postback = new class($data) {
            public function __construct(private string $data) {}
            public function getData(): string { return $this->data; }
        };

        return new class($source, $postback, self::REPLY_TOKEN) {
            public function __construct(
                private $source,
                private $postback,
                private string $replyToken,
            ) {}
            public function getSource() { return $this->source; }
            public function getPostback() { return $this->postback; }
            public function getReplyToken(): string { return $this->replyToken; }
        };
    }

    private function makeTextMessageEvent(string $text): \LINE\Webhook\Model\MessageEvent
    {
        $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
            ->shouldReceive('getUserId')->andReturn(self::USER_ID)->getMock();

        $message = \Mockery::mock(\LINE\Webhook\Model\TextMessageContent::class)
            ->shouldReceive('getText')->andReturn($text)->getMock();

        return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getMessage')->andReturn($message)
            ->shouldReceive('getReplyToken')->andReturn(self::REPLY_TOKEN)
            ->getMock();
    }

    private function callHandlePostback(object $event, string $replyToken): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handlePostback');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, $replyToken);
    }

    private function callHandleTextMessage(\LINE\Webhook\Model\MessageEvent $event, string $replyToken): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, $replyToken);
    }
}
