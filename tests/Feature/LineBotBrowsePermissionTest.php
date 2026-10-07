<?php

use App\Contracts\FishServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\CaptureRecord;
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
            ->andReturn(new \App\Models\User)
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
        $this->assertStringContainsString('你的帳號尚未開通瀏覽權限，請聯繫管理者', $messages[0]->getText());
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
        $this->assertStringContainsString('你的帳號尚未開通瀏覽權限，請聯繫管理者', $messages[0]->getText());
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

    public static function audioActions(): array
    {
        return [['play_audio'], ['no_audio']];
    }

    /** @dataProvider audioActions */
    public function test_guest_and_viewer_are_blocked_from_audio_postbacks(string $action): void
    {
        foreach (['guest', 'viewer'] as $role) {
            $this->lineUserService->shouldReceive('getRole')->once()->andReturn($role);
            $messages = $this->captureReply();

            $this->invoke('handlePostback', $this->postbackEvent("action={$action}"), self::REPLY_TOKEN);

            $this->assertCount(1, $messages);
            $this->assertStringContainsString('此功能僅限田調人員使用', $messages[0]->getText());
        }
    }

    /** @dataProvider audioRoles */
    public function test_editor_and_admin_can_play_audio_postbacks(string $role): void
    {
        $fish = Fish::factory()->create(['audio_filename' => 'line-audio.m4a']);
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn($role);
        $messages = $this->captureReply();

        $this->invoke(
            'handlePostback',
            $this->postbackEvent("action=play_audio&fish_id={$fish->id}&fish_name=測試魚"),
            self::REPLY_TOKEN
        );

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\AudioMessage::class, $messages[1]);
    }

    public static function audioRoles(): array
    {
        return [['editor'], ['admin']];
    }

    public function test_audio_action_inventory_is_explicit_and_complete(): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('audioProtectedActions');
        $method->setAccessible(true);

        $this->assertSame(array_column(self::audioActions(), 0), $method->invoke($this->controller));
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

    /** @dataProvider locationVisibilityRoles */
    public function test_view_captures_postback_respects_location_visibility(string $role, bool $shouldSeeLocation): void
    {
        $fish = Fish::factory()->create(['name' => '測試地名魚']);
        CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'location' => 'ZZLOCATIONMARK',
            'tribe' => config('fish_options.tribes')[0],
        ]);
        $this->lineUserService->shouldReceive('getRole')->once()->andReturn($role);
        $messages = $this->captureReply();

        $this->invoke(
            'handlePostback',
            $this->postbackEvent(http_build_query([
                'action' => 'view_captures',
                'fish_id' => $fish->id,
                'fish_name' => $fish->name,
            ])),
            self::REPLY_TOKEN
        );

        $json = json_encode($messages[0]->jsonSerialize(), JSON_UNESCAPED_UNICODE);
        $this->assertSame($shouldSeeLocation, str_contains($json, 'ZZLOCATIONMARK'));
        $this->assertStringContainsString(config('fish_options.tribes')[0], $json);
    }

    public static function locationVisibilityRoles(): array
    {
        return [
            ['viewer', false],
            ['editor', true],
            ['admin', true],
        ];
    }

    private function captureReply(): \ArrayObject
    {
        $messages = new \ArrayObject;
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
            if ($message instanceof TextMessage && str_contains($message->getText(), '你的帳號尚未開通瀏覽權限，請聯繫管理者')) {
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
        $postback = new class($data)
        {
            public function __construct(private string $data) {}

            public function getData(): string
            {
                return $this->data;
            }
        };

        return new class($source, $postback)
        {
            public function __construct(private object $source, private object $postback) {}

            public function getSource(): object
            {
                return $this->source;
            }

            public function getPostback(): object
            {
                return $this->postback;
            }
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
        return new class(self::USER_ID)
        {
            public function __construct(private string $userId) {}

            public function getUserId(): string
            {
                return $this->userId;
            }
        };
    }
}
