<?php

use App\Contracts\FishServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Services\LineBotService;
use App\Services\UploadService;
use App\Contracts\StorageServiceInterface;
use App\Contracts\LineUserServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['line.channel_secret' => 'test_channel_secret']);
    config(['line.channel_access_token' => 'test_access_token']);
    config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino', 'iranmeilek', 'iratay', 'yayo']]);
    config(['fish_options.capture_methods' => [
        'mapazat' => 'mapazat 網魚',
        'mamasil' => 'mamasil 白天釣魚',
        'mamacik' => 'mamacik 夜間釣魚',
    ]]);
    config(['fish_options.batch_upload.max_files_mobile' => 5]);

    Cache::flush();

    $this->userId = 'test_line_batch_capture_user';
    $this->replyToken = 'test_line_batch_capture_reply';

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
});

function makeBatchPostbackEvent(string $userId, string $replyToken, string $data): object
{
    $source = new class($userId) {
        public function __construct(private string $userId) {}
        public function getUserId(): string { return $this->userId; }
    };

    $postback = new class($data) {
        public function __construct(private string $data) {}
        public function getData(): string { return $this->data; }
    };

    return new class($source, $postback, $replyToken) {
        public function __construct(private $source, private $postback, private string $replyToken) {}
        public function getSource() { return $this->source; }
        public function getPostback() { return $this->postback; }
        public function getReplyToken(): string { return $this->replyToken; }
    };
}

function makeBatchTextMessageEvent(string $userId, string $replyToken, string $text): \LINE\Webhook\Model\MessageEvent
{
    $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
        ->shouldReceive('getUserId')
        ->andReturn($userId)
        ->getMock();

    $message = \Mockery::mock(\LINE\Webhook\Model\TextMessageContent::class)
        ->shouldReceive('getText')
        ->andReturn($text)
        ->getMock();

    return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
        ->shouldReceive('getSource')->andReturn($source)
        ->shouldReceive('getMessage')->andReturn($message)
        ->shouldReceive('getReplyToken')->andReturn($replyToken)
        ->getMock();
}

function makeBatchImageMessageEvent(string $userId, string $replyToken, string $messageId): \LINE\Webhook\Model\MessageEvent
{
    $source = \Mockery::mock(\LINE\Webhook\Model\UserSource::class)
        ->shouldReceive('getUserId')
        ->andReturn($userId)
        ->getMock();

    $message = \Mockery::mock(\LINE\Webhook\Model\ImageMessageContent::class)
        ->shouldReceive('getId')
        ->andReturn($messageId)
        ->getMock();

    return \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
        ->shouldReceive('getSource')->andReturn($source)
        ->shouldReceive('getMessage')->andReturn($message)
        ->shouldReceive('getReplyToken')->andReturn($replyToken)
        ->getMock();
}

function invokeProtected(object $controller, string $methodName, mixed ...$args): mixed
{
    $method = (new \ReflectionClass($controller))->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invoke($controller, ...$args);
}

it('stores uploaded images for LINE batch capture flow and prompts finish upload', function () {
    $fish = Fish::factory()->create();

    Cache::put("line_user_{$this->userId}_batch_capture_state", 'waiting_batch_capture_image', now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_fish", $fish->id, now()->addMinutes(5));

    $this->mockLineBotService
        ->shouldReceive('getMessageContent')
        ->once()
        ->with('img-message-1')
        ->andReturn('fake-image-binary');

    $mockUploadService = \Mockery::mock(\App\Services\LineUploadService::class);
    $mockUploadService->shouldReceive('uploadLineImage')
        ->once()
        ->with('fake-image-binary')
        ->andReturn('line-batch-1.jpg');
    $this->app->instance(\App\Services\LineUploadService::class, $mockUploadService);

    $replied = [];
    $this->mockLineBotService
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function ($token, $messages) use (&$replied) {
            $replied = $messages;
        });

    invokeProtected(
        $this->controller,
        'handleImageMessage',
        makeBatchImageMessageEvent($this->userId, $this->replyToken, 'img-message-1'),
        $this->replyToken
    );

    expect(Cache::get("line_user_{$this->userId}_batch_capture_images"))->toBe(['line-batch-1.jpg']);
    expect(Cache::get("line_user_{$this->userId}_batch_capture_state"))->toBe('waiting_batch_capture_more_images');
    expect($replied)->toHaveCount(1);
    expect($replied[0]->getAltText())->toContain('已收第 1 張圖片');
});

it('offers recent-session reuse or manual entry after finishing LINE batch capture uploads', function () {
    $fish = Fish::factory()->create();
    $recentFish = Fish::factory()->create();
    CaptureRecord::factory()->create([
        'fish_id' => $recentFish->id,
        'tribe' => 'iranmeilek',
        'location' => '大武溪',
        'capture_method' => 'mamasil',
        'capture_date' => '2026-05-03',
        'notes' => '上次備註',
    ]);

    Cache::put("line_user_{$this->userId}_batch_capture_state", 'waiting_batch_capture_more_images', now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_fish", $fish->id, now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_images", ['a.jpg', 'b.jpg'], now()->addMinutes(5));

    $replied = [];
    $this->mockLineBotService
        ->shouldReceive('replyMessage')
        ->once()
        ->andReturnUsing(function ($token, $messages) use (&$replied) {
            $replied = $messages;
        });

    invokeProtected(
        $this->controller,
        'handlePostback',
        makeBatchPostbackEvent($this->userId, $this->replyToken, 'action=finish_batch_capture_images'),
        $this->replyToken
    );

    expect(Cache::get("line_user_{$this->userId}_batch_capture_state"))->toBe('waiting_batch_capture_source_choice');
    expect($replied)->toHaveCount(1);
    expect($replied[0]->getText())->toContain('套用最近一次');
});

it('creates capture records from the latest session in LINE and uses today as capture_date', function () {
    $fish = Fish::factory()->create();
    $recentFish = Fish::factory()->create();
    CaptureRecord::factory()->create([
        'fish_id' => $recentFish->id,
        'tribe' => 'iranmeilek',
        'location' => '大武溪',
        'capture_method' => 'mamasil',
        'capture_date' => now()->subDay()->toDateString(),
        'notes' => '上次備註',
    ]);

    Cache::put("line_user_{$this->userId}_batch_capture_state", 'waiting_batch_capture_source_choice', now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_fish", $fish->id, now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_images", ['a.jpg', 'b.jpg'], now()->addMinutes(5));

    $this->mockLineBotService
        ->shouldReceive('replyMessage')
        ->once();

    invokeProtected(
        $this->controller,
        'handlePostback',
        makeBatchPostbackEvent($this->userId, $this->replyToken, 'action=use_recent_batch_capture_session'),
        $this->replyToken
    );

    $records = CaptureRecord::where('fish_id', $fish->id)->orderBy('id')->get();

    expect($records)->toHaveCount(2);
    expect($records->pluck('tribe')->unique()->all())->toBe(['iranmeilek']);
    expect($records->pluck('location')->unique()->all())->toBe(['大武溪']);
    expect($records->pluck('capture_method')->unique()->all())->toBe(['mamasil']);
    expect($records->pluck('notes')->unique()->all())->toBe(['上次備註']);
    expect($records->pluck('capture_date')->map->format('Y-m-d')->unique()->all())->toBe([now()->toDateString()]);
    expect(Cache::get("line_user_{$this->userId}_batch_capture_state"))->toBeNull();
});

it('creates capture records through manual LINE entry and requires notes', function () {
    $fish = Fish::factory()->create();

    Cache::put("line_user_{$this->userId}_batch_capture_state", 'waiting_batch_capture_source_choice', now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_fish", $fish->id, now()->addMinutes(5));
    Cache::put("line_user_{$this->userId}_batch_capture_images", ['a.jpg', 'b.jpg', 'c.jpg'], now()->addMinutes(5));

    $this->mockLineBotService->shouldReceive('replyMessage')->times(5);

    invokeProtected(
        $this->controller,
        'handlePostback',
        makeBatchPostbackEvent($this->userId, $this->replyToken, 'action=start_manual_batch_capture'),
        $this->replyToken
    );

    invokeProtected(
        $this->controller,
        'handlePostback',
        makeBatchPostbackEvent($this->userId, $this->replyToken, 'action=select_batch_capture_tribe&tribe=ivalino'),
        $this->replyToken
    );

    invokeProtected(
        $this->controller,
        'handleTextMessage',
        makeBatchTextMessageEvent($this->userId, $this->replyToken, '知本溪'),
        $this->replyToken
    );

    invokeProtected(
        $this->controller,
        'handlePostback',
        makeBatchPostbackEvent($this->userId, $this->replyToken, 'action=select_batch_capture_method&capture_method=mapazat'),
        $this->replyToken
    );

    invokeProtected(
        $this->controller,
        'handleTextMessage',
        makeBatchTextMessageEvent($this->userId, $this->replyToken, '這次三張都同一批'),
        $this->replyToken
    );

    $records = CaptureRecord::where('fish_id', $fish->id)->orderBy('id')->get();

    expect($records)->toHaveCount(3);
    expect($records->pluck('tribe')->unique()->all())->toBe(['ivalino']);
    expect($records->pluck('location')->unique()->all())->toBe(['知本溪']);
    expect($records->pluck('capture_method')->unique()->all())->toBe(['mapazat']);
    expect($records->pluck('notes')->unique()->all())->toBe(['這次三張都同一批']);
    expect($records->pluck('capture_date')->map->format('Y-m-d')->unique()->all())->toBe([now()->toDateString()]);
    expect(Cache::get("line_user_{$this->userId}_batch_capture_state"))->toBeNull();
});
