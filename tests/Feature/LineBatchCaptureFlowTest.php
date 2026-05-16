<?php

use App\Contracts\FishServiceInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\Fish;
use App\Services\LineBotService;
use App\Services\LineUploadService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LineBatchCaptureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected const USER_ID = 'batch_capture_user';
    protected const REPLY_TOKEN = 'batch_capture_reply_token';

    protected LineBotController $controller;
    protected \Mockery\MockInterface $mockLineBotService;
    protected \Mockery\MockInterface $mockLineUserService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'line.channel_secret' => 'test_channel_secret',
            'line.channel_access_token' => 'test_access_token',
            'fish_options.tribes' => ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
            'fish_options.capture_methods' => [
                'mapazat' => 'mapazat 網魚',
                'mamasil' => 'mamasil 白天釣魚',
                'mamacik' => 'mamacik 夜間釣魚',
                'mitokzos' => 'mitokzos',
                'mipaltog' => 'mipaltog',
            ],
            'fish_options.batch_upload.max_files_mobile' => 5,
        ]);

        Cache::flush();

        $this->mockLineBotService = \Mockery::mock(LineBotService::class);
        $this->mockLineBotService
            ->shouldReceive('getUserProfile')
            ->andReturn(['displayName' => 'Test User', 'pictureUrl' => null])
            ->byDefault();

        $this->mockLineUserService = \Mockery::mock(LineUserServiceInterface::class);
        $this->mockLineUserService
            ->shouldReceive('upsert')
            ->andReturn(new \App\Models\User())
            ->byDefault();
        $this->mockLineUserService
            ->shouldReceive('getRole')
            ->andReturn('editor')
            ->byDefault();

        $this->controller = new LineBotController(
            $this->mockLineBotService,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $this->mockLineUserService,
            $this->app->make(FishServiceInterface::class)
        );
    }

    private function makePostbackEvent(string $data): object
    {
        $source = new class(self::USER_ID) {
            public function __construct(private string $userId)
            {
            }
            public function getUserId(): string
            {
                return $this->userId;
            }
        };

        $postback = new class($data) {
            public function __construct(private string $data)
            {
            }
            public function getData(): string
            {
                return $this->data;
            }
        };

        return new class($source, $postback) {
            public function __construct(private $source, private $postback)
            {
            }
            public function getSource()
            {
                return $this->source;
            }
            public function getPostback()
            {
                return $this->postback;
            }
        };
    }

    private function makeImageMessageEvent(string $messageId): \LINE\Webhook\Model\MessageEvent
    {
        $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
            ->shouldReceive('getUserId')
            ->andReturn(self::USER_ID)
            ->getMock();

        $message = \Mockery::mock(\LINE\Webhook\Model\ImageMessageContent::class)
            ->shouldReceive('getId')
            ->andReturn($messageId)
            ->getMock();

        return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getMessage')->andReturn($message)
            ->getMock();
    }

    private function makeTextMessageEvent(string $text): \LINE\Webhook\Model\MessageEvent
    {
        $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
            ->shouldReceive('getUserId')
            ->andReturn(self::USER_ID)
            ->getMock();

        $message = \Mockery::mock(\LINE\Webhook\Model\TextMessageContent::class)
            ->shouldReceive('getText')
            ->andReturn($text)
            ->getMock();

        return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getMessage')->andReturn($message)
            ->getMock();
    }

    private function invokeHandlePostback(object $event): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handlePostback');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, self::REPLY_TOKEN);
    }

    private function invokeHandleImageMessage(\LINE\Webhook\Model\MessageEvent $event): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handleImageMessage');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, self::REPLY_TOKEN);
    }

    private function invokeHandleTextMessage(\LINE\Webhook\Model\MessageEvent $event): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, self::REPLY_TOKEN);
    }

    private function captureSingleReply(callable $callback): array
    {
        $replied = [];

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $callback();

        return $replied;
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

    private function footerLabels(array $flexJson): array
    {
        return array_map(
            fn ($item) => $item['action']['label'] ?? null,
            $flexJson['contents']['footer']['contents'] ?? []
        );
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

    public function test_batch_capture_image_upload_returns_summary_flex_card_with_placeholders(): void
    {
        $fish = Fish::factory()->create(['name' => '測試魚']);

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_images', now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));

        $this->mockLineBotService
            ->shouldReceive('getMessageContent')
            ->once()
            ->with('img-message-1')
            ->andReturn('image-binary');

        $mockUploadService = \Mockery::mock(LineUploadService::class);
        $mockUploadService
            ->shouldReceive('uploadLineImage')
            ->once()
            ->with('image-binary')
            ->andReturn('capture-1.jpg');
        $this->app->instance(LineUploadService::class, $mockUploadService);

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandleImageMessage($this->makeImageMessageEvent('img-message-1'));
        });

        $this->assertSame(['capture-1.jpg'], Cache::get('line_user_' . self::USER_ID . '_batch_capture_images'));
        $this->assertSame('waiting_images', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);
        $footerLabels = $this->footerLabels($json);

        $this->assertContains('照片數量：1 張', $texts);
        $this->assertContains('部落：未選擇', $texts);
        $this->assertContains('捕獲方式：未選擇', $texts);
        $this->assertContains('➕ 繼續上傳', $footerLabels);
        $this->assertContains('✅ 圖片上傳完成', $footerLabels);
        $this->assertContains('❌ 取消', $footerLabels);
    }

    public function test_initial_batch_capture_summary_requires_images_before_next_step(): void
    {
        $fish = Fish::factory()->create(['name' => '測試魚']);

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', [], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_form', [], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_images', now()->addMinutes(15));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=continue_batch_capture_upload'));
        });

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);
        $footerLabels = $this->footerLabels($json);

        $this->assertContains('照片數量：0 張', $texts);
        $this->assertContains('請先上傳至少 1 張捕獲照片，全部上傳完成後再進入下一步。', $texts);
        $this->assertNotContains('選擇捕獲部落', $footerLabels);
        $this->assertNotContains('✅ 圖片上傳完成', $footerLabels);
        $this->assertContains('❌ 取消', $footerLabels);
    }

    public function test_opening_tribe_selector_replies_with_flex_buttons_instead_of_quick_reply(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_images', now()->addMinutes(15));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=open_batch_capture_tribe_selector'));
        });

        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $labels = $this->allButtonLabels($json['contents']);

        $this->assertContains('Ivalino', $labels);
        $this->assertContains('Iranmeilek', $labels);
        $this->assertContains('Imowrod', $labels);
        $this->assertArrayNotHasKey('quickReply', $json);
    }

    public function test_finishing_image_upload_transitions_to_tribe_selector(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_images', now()->addMinutes(15));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=finish_batch_capture_upload'));
        });

        $this->assertSame('waiting_tribe_selection', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $labels = $this->allButtonLabels($json['contents']);

        $this->assertContains('Ivalino', $labels);
        $this->assertContains('Iranmeilek', $labels);
    }

    public function test_selecting_tribe_replies_with_summary_card_showing_actual_tribe_value(): void
    {
        $fish = Fish::factory()->create(['name' => '測試魚']);

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_form', [], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_tribe_selection', now()->addMinutes(15));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=select_batch_capture_tribe&tribe=ivalino'));
        });

        $this->assertSame('awaiting_location_prompt', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertSame('ivalino', Cache::get('line_user_' . self::USER_ID . '_batch_capture_form')['tribe']);

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);
        $footerLabels = $this->footerLabels($json);

        $this->assertContains('部落：ivalino', $texts);
        $this->assertContains('地點：未填寫', $texts);
        $this->assertContains('輸入捕獲地點', $footerLabels);
    }

    public function test_opening_capture_method_selector_replies_with_flex_card_buttons(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_form', [
            'tribe' => 'ivalino',
            'location' => 'Vanes',
        ], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'awaiting_method_prompt', now()->addMinutes(15));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=open_batch_capture_method_selector'));
        });

        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $labels = $this->allButtonLabels($json['contents']);

        $this->assertContains('mapazat 網魚', $labels);
        $this->assertContains('mamasil 白天釣魚', $labels);
        $this->assertContains('mamacik 夜間釣魚', $labels);
        $this->assertArrayNotHasKey('quickReply', $json);
    }

    public function test_confirm_batch_capture_creates_records_with_notes_using_progressive_summary_cards(): void
    {
        $fish = Fish::factory()->create(['name' => 'Test Fish 8288']);

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'awaiting_location_input', now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg', 'capture-2.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_form', ['tribe' => 'ivalino'], now()->addMinutes(15));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->times(8)
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->invokeHandleTextMessage($this->makeTextMessageEvent('Vanes'));
        $this->assertSame('awaiting_method_prompt', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=open_batch_capture_method_selector'));
        $this->invokeHandlePostback($this->makePostbackEvent('action=select_batch_capture_method&capture_method=mapazat'));
        $this->assertSame('awaiting_date_prompt', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=open_batch_capture_date_selector'));
        $this->invokeHandlePostback($this->makePostbackEvent('action=set_batch_capture_date&value=yesterday'));
        $this->assertSame('awaiting_notes_prompt', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=prompt_batch_capture_notes'));
        $this->invokeHandleTextMessage($this->makeTextMessageEvent('金鰲跟魯凱'));
        $this->assertSame('waiting_confirm', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=confirm_batch_capture_record'));

        $this->assertDatabaseHas('capture_records', [
            'fish_id' => $fish->id,
            'image_path' => 'capture-1.jpg',
            'tribe' => 'ivalino',
            'location' => 'Vanes',
            'capture_method' => 'mapazat',
            'capture_date' => now()->subDay()->toDateString(),
            'notes' => '金鰲跟魯凱',
        ]);
        $this->assertDatabaseHas('capture_records', [
            'fish_id' => $fish->id,
            'image_path' => 'capture-2.jpg',
            'tribe' => 'ivalino',
            'location' => 'Vanes',
            'capture_method' => 'mapazat',
            'capture_date' => now()->subDay()->toDateString(),
            'notes' => '金鰲跟魯凱',
        ]);

        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_fish'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_images'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_form'));
    }
}
