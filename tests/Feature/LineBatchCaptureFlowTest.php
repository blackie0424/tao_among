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

    public function test_batch_capture_image_upload_replies_with_flex_card_buttons(): void
    {
        $fish = Fish::factory()->create();

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

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandleImageMessage($this->makeImageMessageEvent('img-message-1'));

        $this->assertSame(['capture-1.jpg'], Cache::get('line_user_' . self::USER_ID . '_batch_capture_images'));
        $this->assertSame('waiting_images', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = json_decode(json_encode($replied[0]), true);
        $buttons = array_map(
            fn ($item) => $item['action']['label'] ?? null,
            $json['contents']['footer']['contents'] ?? []
        );

        $this->assertContains('➕ 繼續上傳', $buttons);
        $this->assertContains('✅ 完成上傳', $buttons);
        $this->assertContains('❌ 取消', $buttons);
    }

    public function test_confirm_batch_capture_creates_records_with_notes(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_' . self::USER_ID . '_batch_capture_state', 'waiting_location', now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_fish', $fish->id, now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_images', ['capture-1.jpg', 'capture-2.jpg'], now()->addMinutes(15));
        Cache::put('line_user_' . self::USER_ID . '_batch_capture_form', ['tribe' => 'ivalino'], now()->addMinutes(15));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->times(5)
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->invokeHandleTextMessage($this->makeTextMessageEvent('大武溪上游'));
        $this->assertSame('waiting_capture_method', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=select_batch_capture_method&capture_method=mapazat'));
        $this->assertSame('waiting_capture_date', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=set_batch_capture_date&value=today'));
        $this->assertSame('waiting_notes', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandleTextMessage($this->makeTextMessageEvent('溪水較急，3 張皆同地點捕獲'));
        $this->assertSame('waiting_confirm', Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));

        $this->invokeHandlePostback($this->makePostbackEvent('action=confirm_batch_capture_record'));

        $this->assertDatabaseHas('capture_records', [
            'fish_id' => $fish->id,
            'image_path' => 'capture-1.jpg',
            'tribe' => 'ivalino',
            'location' => '大武溪上游',
            'capture_method' => 'mapazat',
            'capture_date' => now()->toDateString(),
            'notes' => '溪水較急，3 張皆同地點捕獲',
        ]);
        $this->assertDatabaseHas('capture_records', [
            'fish_id' => $fish->id,
            'image_path' => 'capture-2.jpg',
            'tribe' => 'ivalino',
            'location' => '大武溪上游',
            'capture_method' => 'mapazat',
            'capture_date' => now()->toDateString(),
            'notes' => '溪水較急，3 張皆同地點捕獲',
        ]);

        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_fish'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_images'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_batch_capture_form'));
    }
}
