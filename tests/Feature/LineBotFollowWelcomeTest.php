<?php

use App\Contracts\FishServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\User;
use App\Services\UploadService;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Webhook\Model\FollowEvent;
use LINE\Webhook\Model\UserSource;
use Tests\TestCase;

class LineBotFollowWelcomeTest extends TestCase
{
    private const USER_ID = 'line-follow-user';

    private const REPLY_TOKEN = 'line-follow-reply';

    private \Mockery\MockInterface $messagingClient;

    private \Mockery\MockInterface $lineUserService;

    private LineBotController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messagingClient = \Mockery::mock(LineMessagingClientInterface::class);
        $this->lineUserService = \Mockery::mock(LineUserServiceInterface::class);

        $this->controller = new LineBotController(
            $this->messagingClient,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $this->lineUserService,
            $this->app->make(FishServiceInterface::class),
        );
    }

    public function test_guest_follow_receives_account_pending_welcome(): void
    {
        $messages = $this->expectProfileUpsertAndCaptureWelcome('guest');

        $this->invokeFollow();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(TextMessage::class, $messages[0]);
        $this->assertStringContainsString('帳號已建立', $messages[0]->getText());
        $this->assertStringContainsString('尚未開通瀏覽權限', $messages[0]->getText());
        $this->assertStringContainsString('聯繫管理者', $messages[0]->getText());
    }

    public function test_existing_viewer_follow_receives_general_welcome(): void
    {
        $messages = $this->expectProfileUpsertAndCaptureWelcome('viewer');

        $this->invokeFollow();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(TextMessage::class, $messages[0]);
        $this->assertStringContainsString('歡迎回來', $messages[0]->getText());
        $this->assertStringNotContainsString('尚未開通', $messages[0]->getText());
    }

    public function test_failed_profile_upsert_does_not_claim_account_was_created(): void
    {
        $this->messagingClient->shouldReceive('getUserProfile')
            ->once()
            ->with(self::USER_ID)
            ->andThrow(new RuntimeException('LINE unavailable'));
        $this->messagingClient->shouldNotReceive('replyMessage');
        $this->lineUserService->shouldNotReceive('upsert');

        $this->invokeFollow();
    }

    private function expectProfileUpsertAndCaptureWelcome(string $role): \ArrayObject
    {
        $this->messagingClient->shouldReceive('getUserProfile')
            ->once()
            ->with(self::USER_ID)
            ->andReturn([
                'displayName' => 'Test User',
                'pictureUrl' => 'https://example.com/avatar.jpg',
            ]);

        $user = new User();
        $user->role = $role;
        $this->lineUserService->shouldReceive('upsert')
            ->once()
            ->with(self::USER_ID, 'Test User', 'https://example.com/avatar.jpg')
            ->andReturn($user);

        $messages = new \ArrayObject();
        $this->messagingClient->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'))
            ->andReturnUsing(function ($replyToken, $replyMessages) use ($messages): void {
                $messages->exchangeArray($replyMessages);
            });

        return $messages;
    }

    private function invokeFollow(): void
    {
        $source = \Mockery::mock(UserSource::class)
            ->shouldReceive('getUserId')
            ->andReturn(self::USER_ID)
            ->getMock();

        $event = \Mockery::mock(FollowEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getReplyToken')->andReturn(self::REPLY_TOKEN)
            ->getMock();

        $method = (new ReflectionClass($this->controller))->getMethod('handleFollowEvent');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event);
    }
}
