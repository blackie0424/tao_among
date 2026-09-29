<?php

use App\Contracts\FishServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\Fish;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use LINE\Clients\MessagingApi\Model\TextMessage;
use Tests\TestCase;

class LineBotBrowsePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = 'line-browse-user';

    private const REPLY_TOKEN = 'line-browse-reply';

    private LineBotController $controller;

    private \Mockery\MockInterface $messagingClient;

    private \Mockery\MockInterface $lineUserService;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['fish_options.tribes' => ['iraraley', 'imowrod']]);

        $this->messagingClient = \Mockery::mock(LineMessagingClientInterface::class);
        $this->messagingClient->shouldReceive('getUserProfile')
            ->andReturn(['displayName' => 'Test User', 'pictureUrl' => null])
            ->byDefault();

        $this->lineUserService = \Mockery::mock(LineUserServiceInterface::class);
        $this->lineUserService->shouldReceive('upsert')
            ->andReturn(new \App\Models\User())
            ->byDefault();

        $this->controller = new LineBotController(
            $this->messagingClient,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $this->lineUserService,
            $this->app->make(FishServiceInterface::class),
        );
    }

    public static function browseActions(): array
    {
        return array_map(
            fn (string $action) => [$action],
            [
                'browse_oyod',
                'browse_rahet',
                'browse_tribes_menu',
                'browse_tribe_data',
                'random_browse',
                'browse_next',
                'browse_knowledge',
                'random_unknown_fish',
                'view_captures',
                'play_audio',
                'no_audio',
            ],
        );
    }

    public static function browsingRoles(): array
    {
        return [['viewer'], ['editor'], ['admin']];
    }

    public static function controlActions(): array
    {
        return [
            ['skip', null],
            ['cancel_create_fish', '已取消新增魚類'],
            ['cancel_add_knowledge', '已取消新增進階知識'],
        ];
    }

    /** @dataProvider browseActions */
    public function test_guest_is_blocked_from_every_browse_postback(string $action): void
    {
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn('guest');
        $messages = $this->captureReply();

        $this->invoke('handlePostback', $this->postbackEvent("action={$action}"), self::REPLY_TOKEN);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(TextMessage::class, $messages[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $messages[0]->getText());
    }

    /** @dataProvider guestSearchTerms */
    public function test_guest_text_search_is_blocked_without_fish_data(string $text): void
    {
        Fish::factory()->create(['name' => '敏感魚類資料']);
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn('guest');
        $messages = $this->captureReply();

        $this->invoke('handleTextMessage', $this->textEvent($text), self::REPLY_TOKEN);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(TextMessage::class, $messages[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $messages[0]->getText());
        $this->assertStringNotContainsString('敏感魚類資料', $messages[0]->getText());
    }

    public static function guestSearchTerms(): array
    {
        return [['敏感魚類資料'], ['隨機命名']];
    }

    public function test_browse_action_inventory_is_explicit_and_complete(): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('browseProtectedActions');
        $method->setAccessible(true);

        $this->assertSame(array_column(self::browseActions(), 0), $method->invoke($this->controller));
    }

    /** @dataProvider browsingRoles */
    public function test_existing_browsing_roles_can_use_text_search(string $role): void
    {
        Fish::factory()->create(['name' => '測試魚']);
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn($role);
        $messages = $this->captureReply();

        $this->invoke('handleTextMessage', $this->textEvent('測試魚'), self::REPLY_TOKEN);

        $this->assertNotEmpty($messages);
        $this->assertFalse($this->containsBrowseDenial($messages));
    }

    /** @dataProvider browsingRoles */
    public function test_existing_browsing_roles_can_use_browse_postbacks(string $role): void
    {
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn($role);
        $messages = $this->captureReply();

        $this->invoke('handlePostback', $this->postbackEvent('action=browse_tribes_menu'), self::REPLY_TOKEN);

        $this->assertNotEmpty($messages);
        $this->assertFalse($this->containsBrowseDenial($messages));
    }

    /** @dataProvider controlActions */
    public function test_guest_can_still_use_control_actions(string $action, ?string $expectedText): void
    {
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn('guest');
        $messages = [];

        if ($expectedText === null) {
            $this->messagingClient->shouldNotReceive('replyMessage');
        } else {
            $messages = $this->captureReply();
        }

        $this->invoke('handlePostback', $this->postbackEvent("action={$action}"), self::REPLY_TOKEN);

        if ($expectedText !== null) {
            $this->assertStringContainsString($expectedText, $messages[0]->getText());
        }
    }

    private function captureReply(): \ArrayObject
    {
        $messages = new \ArrayObject();
        $this->messagingClient->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $replyMessages) use (&$messages): void {
                $messages->exchangeArray($replyMessages);
            });

        return $messages;
    }

    private function containsBrowseDenial(iterable $messages): bool
    {
        foreach ($messages as $message) {
            if ($message instanceof TextMessage && str_contains($message->getText(), '沒有此功能的使用權限')) {
                return true;
            }
        }

        return false;
    }

    private function invoke(string $methodName, ...$arguments): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod($methodName);
        $method->setAccessible(true);
        $method->invoke($this->controller, ...$arguments);
    }

    private function postbackEvent(string $data): object
    {
        $source = $this->source();
        $postback = new class($data) {
            public function __construct(private string $data) {}
            public function getData(): string { return $this->data; }
        };

        return new class($source, $postback) {
            public function __construct(private object $source, private object $postback) {}
            public function getSource(): object { return $this->source; }
            public function getPostback(): object { return $this->postback; }
        };
    }

    private function textEvent(string $text): \LINE\Webhook\Model\MessageEvent
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

    private function source(): object
    {
        return new class(self::USER_ID) {
            public function __construct(private string $userId) {}
            public function getUserId(): string { return $this->userId; }
        };
    }
}
