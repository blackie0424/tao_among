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
 * 測試「新增魚類」流程
 *
 * 命名完成後即建立 Fish 資料，捕獲紀錄表單改用 LineCreateFishFormFlowService（與批次捕獲相同的 UI）。
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
    // 命名完成 → 建立 Fish + 啟動表單流程
    // =====================================================

    /** @test */
    public function test_default_name_creates_fish_and_starts_form_session(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->once();

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);
        $this->assertEquals('我不知道', Fish::first()->name);

        // old create_fish cache cleared
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_images"));

        // form session started in LineCreateFishFormStateStore
        $this->assertEquals('waiting_tribe_selection', Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));
    }

    /** @test */
    public function test_custom_name_creates_fish_with_given_name_and_starts_form_session(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_custom_name', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->once();

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('黑鰭燕魟'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);
        $this->assertEquals('黑鰭燕魟', Fish::first()->name);
        $this->assertEquals('waiting_tribe_selection', Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));
    }

    /** @test */
    public function test_name_selection_sends_tribe_selection_flex_card(): void
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

    // =====================================================
    // 取消流程 → 刪除孤兒 Fish
    // =====================================================

    /** @test */
    public function test_cancel_create_fish_during_form_deletes_orphaned_fish(): void
    {
        // start a form session (fish created, no capture records)
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->twice();

        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);

        // cancel_create_fish while form is active
        $this->callHandlePostback(
            $this->makePostbackEvent('action=cancel_create_fish'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 0);
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));
    }

    /** @test */
    public function test_cancel_batch_capture_during_create_fish_form_deletes_orphaned_fish(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->twice();

        // start form
        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 1);

        // cancel via batch capture cancel button
        $this->callHandlePostback(
            $this->makePostbackEvent('action=cancel_batch_capture_record'),
            self::REPLY_TOKEN
        );

        $this->assertDatabaseCount('fish', 0);
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));
    }

    // =====================================================
    // 完整流程：命名 → 表單 → 確認 → Fish + CaptureRecord
    // =====================================================

    /** @test */
    public function test_full_create_fish_flow_creates_capture_records_and_sets_display_record(): void
    {
        // Step 1: start with images and name choice state
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg', 'img2.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->byDefault();

        // Step 2: select default name → fish created, form session started
        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        $fish = Fish::first();
        $this->assertNotNull($fish);
        $fishId = $fish->id;

        // Step 3: select tribe
        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_batch_capture_tribe&tribe=ivalino'),
            self::REPLY_TOKEN
        );

        // Step 4: input location
        $formState = Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state");
        Cache::put("line_user_" . self::USER_ID . "_create_fish_form_state", 'awaiting_location_input', now()->addMinutes(15));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_form_form", array_merge(
            Cache::get("line_user_" . self::USER_ID . "_create_fish_form_form", []),
            ['tribe' => 'ivalino']
        ), now()->addMinutes(15));

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('小蘭嶼南側礁石'),
            self::REPLY_TOKEN
        );

        // Step 5: select method
        $this->callHandlePostback(
            $this->makePostbackEvent('action=select_batch_capture_method&capture_method=mapazat'),
            self::REPLY_TOKEN
        );

        // Step 6: select date (today)
        $this->callHandlePostback(
            $this->makePostbackEvent('action=set_batch_capture_date&date=today'),
            self::REPLY_TOKEN
        );

        // Step 7: skip notes → move to confirm
        $this->callHandlePostback(
            $this->makePostbackEvent('action=skip_batch_capture_notes'),
            self::REPLY_TOKEN
        );

        // Step 8: confirm
        $this->callHandlePostback(
            $this->makePostbackEvent('action=confirm_batch_capture_record'),
            self::REPLY_TOKEN
        );

        // fish still exists (not orphaned)
        $this->assertDatabaseCount('fish', 1);
        // capture records created (one per image)
        $this->assertDatabaseCount('capture_records', 2);

        $fish->refresh();
        $this->assertNotNull($fish->display_capture_record_id);

        // form state cleared
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));
    }

    // =====================================================
    // 取消期間清除所有 Cache
    // =====================================================

    /** @test */
    public function test_cancel_during_capture_flow_clears_all_new_cache_keys(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_name_choice', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_images", ['img1.jpg'], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('replyMessage')->byDefault();

        // start form
        $this->callHandlePostback(
            $this->makePostbackEvent('action=create_fish_with_default_name'),
            self::REPLY_TOKEN
        );

        // cancel
        $this->callHandlePostback(
            $this->makePostbackEvent('action=cancel_create_fish'),
            self::REPLY_TOKEN
        );

        $createFishPrefix = "line_user_" . self::USER_ID . "_create_fish_";
        $this->assertNull(Cache::get("{$createFishPrefix}state"));
        $this->assertNull(Cache::get("{$createFishPrefix}images"));

        $formPrefix = "line_user_" . self::USER_ID . "_create_fish_form_";
        $this->assertNull(Cache::get("{$formPrefix}state"));
        $this->assertNull(Cache::get("{$formPrefix}fish"));
        $this->assertNull(Cache::get("{$formPrefix}images"));
        $this->assertNull(Cache::get("{$formPrefix}form"));
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

    /** @test */
    public function test_fish_service_create_fish_with_images_creates_fish_without_capture_records(): void
    {
        $service = $this->app->make(FishService::class);

        $fish = $service->createFishWithImages('黑鰭燕魟', ['img1.jpg', 'img2.jpg']);

        $this->assertDatabaseCount('fish', 1);
        $this->assertDatabaseCount('capture_records', 0);
        $this->assertEquals('黑鰭燕魟', $fish->name);
        $this->assertEquals('img1.jpg', $fish->image);
        $this->assertNull($fish->display_capture_record_id);
    }

    // =====================================================
    // imageSet 多圖自動完成流程
    // =====================================================

    /** @test */
    public function test_imageset_partial_upload_shows_progress_and_stays_in_waiting_state(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_image', now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('getMessageContent')->with('msg-1')->andReturn('blob1');
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineImage')->with('blob1')->andReturn('a.jpg');
        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

        $this->mockLineBotService->shouldReceive('replyMessage')->once();

        $this->callHandleImageMessage(
            $this->makeImageMessageEvent('msg-1', ['id' => 'set-x', 'index' => 1, 'total' => 3]),
            self::REPLY_TOKEN
        );

        $state = Cache::get("line_user_" . self::USER_ID . "_create_fish_state");
        $indexed = Cache::get("line_user_" . self::USER_ID . "_create_fish_indexed_images");

        $this->assertContains($state, ['waiting_image', 'waiting_more_images']);
        $this->assertNotNull($indexed);
        $this->assertSame('a.jpg', $indexed['indexed'][1]);
    }

    /** @test */
    public function test_imageset_auto_transitions_to_name_choice_when_all_images_received(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_more_images', now()->addMinutes(5));
        Cache::put("line_user_" . self::USER_ID . "_create_fish_indexed_images", [
            'set_id' => 'set-x',
            'indexed' => [1 => 'a.jpg', 2 => 'b.jpg'],
            'total'   => 3,
        ], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('getMessageContent')->with('msg-3')->andReturn('blob3');
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineImage')->with('blob3')->andReturn('c.jpg');
        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

        $this->mockLineBotService->shouldReceive('replyMessage')->once();

        $this->callHandleImageMessage(
            $this->makeImageMessageEvent('msg-3', ['id' => 'set-x', 'index' => 3, 'total' => 3]),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_name_choice', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));

        $images = Cache::get("line_user_" . self::USER_ID . "_create_fish_images");
        $this->assertEquals(['a.jpg', 'b.jpg', 'c.jpg'], $images);
        $this->assertNull(Cache::get("line_user_" . self::USER_ID . "_create_fish_indexed_images"));
    }

    /** @test */
    public function test_imageset_images_are_sorted_by_index_regardless_of_arrival_order(): void
    {
        Cache::put("line_user_" . self::USER_ID . "_create_fish_state", 'waiting_more_images', now()->addMinutes(5));
        // index 2 arrived first (a.jpg), then index 1 arriving now (b.jpg)
        Cache::put("line_user_" . self::USER_ID . "_create_fish_indexed_images", [
            'set_id' => 'set-y',
            'indexed' => [2 => 'second.jpg'],
            'total'   => 2,
        ], now()->addMinutes(5));

        $this->mockLineBotService->shouldReceive('getMessageContent')->with('msg-1')->andReturn('blob1');
        $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
        $mockUploadService->shouldReceive('uploadLineImage')->with('blob1')->andReturn('first.jpg');
        $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

        $this->mockLineBotService->shouldReceive('replyMessage')->once();

        $this->callHandleImageMessage(
            $this->makeImageMessageEvent('msg-1', ['id' => 'set-y', 'index' => 1, 'total' => 2]),
            self::REPLY_TOKEN
        );

        $this->assertEquals('waiting_name_choice', Cache::get("line_user_" . self::USER_ID . "_create_fish_state"));

        $images = Cache::get("line_user_" . self::USER_ID . "_create_fish_images");
        // index 1 (first.jpg) should be before index 2 (second.jpg)
        $this->assertEquals(['first.jpg', 'second.jpg'], $images);
    }

    // =====================================================
    // 輔助方法
    // =====================================================

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

    private function callHandleImageMessage(\LINE\Webhook\Model\MessageEvent $event, string $replyToken): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handleImageMessage');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, $replyToken);
    }

    /**
     * @param array{id:string,index:int,total:int}|null $imageSet
     */
    private function makeImageMessageEvent(string $messageId, ?array $imageSet = null): \LINE\Webhook\Model\MessageEvent
    {
        $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
            ->shouldReceive('getUserId')->andReturn(self::USER_ID)->getMock();

        $sdkImageSet = null;
        if ($imageSet !== null) {
            $sdkImageSet = \Mockery::mock(\LINE\Webhook\Model\ImageSet::class);
            $sdkImageSet->shouldReceive('getId')->andReturn($imageSet['id']);
            $sdkImageSet->shouldReceive('getIndex')->andReturn($imageSet['index']);
            $sdkImageSet->shouldReceive('getTotal')->andReturn($imageSet['total']);
        }

        $message = \Mockery::mock(\LINE\Webhook\Model\ImageMessageContent::class);
        $message->shouldReceive('getId')->andReturn($messageId);
        $message->shouldReceive('getImageSet')->andReturn($sdkImageSet);

        return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getMessage')->andReturn($message)
            ->shouldReceive('getReplyToken')->andReturn(self::REPLY_TOKEN)
            ->getMock();
    }

    private function flexToArray(object $message): array
    {
        return json_decode(json_encode($message), true);
    }

    private function flattenTexts(array $node): array
    {
        $texts = [];
        if (($node['type'] ?? null) === 'text' && isset($node['text'])) {
            $texts[] = $node['text'];
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $texts = array_merge($texts, $this->flattenTexts($item));
                        }
                    }
                } else {
                    $texts = array_merge($texts, $this->flattenTexts($value));
                }
            }
        }
        return $texts;
    }

    private function allButtonLabels(array $node): array
    {
        $labels = [];
        if (($node['type'] ?? null) === 'button' && isset($node['action']['label'])) {
            $labels[] = $node['action']['label'];
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $labels = array_merge($labels, $this->allButtonLabels($item));
                        }
                    }
                } else {
                    $labels = array_merge($labels, $this->allButtonLabels($value));
                }
            }
        }
        return $labels;
    }

    // =====================================================
    // Session Picker（新增魚類流程）
    // =====================================================

    /** @test */
    public function test_start_form_session_with_recent_sessions_shows_session_picker(): void
    {
        $existingFish = Fish::factory()->create();
        CaptureRecord::factory()->create([
            'fish_id'        => $existingFish->id,
            'tribe'          => 'iranmeilek',
            'location'       => '溪邊釣點',
            'capture_method' => 'mamasil',
            'capture_date'   => '2026-06-29',
        ]);

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

        $this->assertSame('waiting_session_selection', Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));

        $this->assertCount(1, $capturedMessages);
        $json = $this->flexToArray($capturedMessages[0]);
        $this->assertSame('carousel', $json['contents']['type']);

        $allTexts = $this->flattenTexts($json['contents']);
        $this->assertContains('溪邊釣點', $allTexts);

        $allLabels = $this->allButtonLabels($json['contents']);
        $this->assertContains('使用此筆', $allLabels);
        $this->assertContains('手動填寫', $allLabels);
    }

    /** @test */
    public function test_start_form_session_without_sessions_shows_tribe_card(): void
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

        $this->assertSame('waiting_tribe_selection', Cache::get("line_user_" . self::USER_ID . "_create_fish_form_state"));

        $json = $this->flexToArray($capturedMessages[0]);
        $labels = $this->allButtonLabels($json['contents']);
        $this->assertContains('Ivalino', $labels);
    }
}
