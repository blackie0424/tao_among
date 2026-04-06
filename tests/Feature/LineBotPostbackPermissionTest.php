<?php

use App\Http\Controllers\LineBotController;
use App\Http\Controllers\ApiFishController;
use App\Services\LineBotService;
use App\Services\UploadService;
use App\Contracts\StorageServiceInterface;
use App\Contracts\LineUserServiceInterface;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * TDD 安全性測試：後端 postback 權限把關
 *
 * 問題背景：
 *   UI 層透過 isEditor 控制是否顯示「修改名稱」與「提供發音」按鈕。
 *   但若使用者之前已看到舊訊息（角色為 editor 時的圖卡），
 *   在降為 viewer 後仍可點選舊按鈕觸發 postback，後端若無驗證則會直接執行。
 *
 * 修正需求：
 *   - `start_rename`   → 後端須檢查 isEditor，viewer 應被拒絕
 *   - `start_add_audio`→ 後端須檢查 isEditor，viewer 應被拒絕
 *   - 已有保護的 `start_create_fish`、`provide_clue` 維持不變
 */
class LineBotPostbackPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected const USER_ID  = 'test_user_permission';
    protected const REPLY_TOKEN = 'test_reply_token_perm';

    protected LineBotController $controller;
    protected \Mockery\MockInterface $mockLineBotService;
    protected \Mockery\MockInterface $mockLineUserService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['line.channel_secret'        => 'test_channel_secret']);
        config(['line.channel_access_token'  => 'test_access_token']);
        config(['fish_options.tribes'        => ['iraraley', 'imowrod', 'ivalino', 'iranmeilek', 'iratay', 'yayo']]);

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

        $this->controller = new LineBotController(
            $this->mockLineBotService,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $this->mockLineUserService
        );
    }

    // ------------------------------------------------------------------
    // 輔助方法
    // ------------------------------------------------------------------

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
            public function getSource()  { return $this->source; }
            public function getPostback() { return $this->postback; }
            public function getReplyToken(): string { return $this->replyToken; }
        };
    }

    private function invokeHandlePostback(object $event): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('handlePostback');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, self::REPLY_TOKEN);
    }

    private function captureRepliedMessages(): array
    {
        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });
        return $replied;
    }

    // ------------------------------------------------------------------
    // 已有保護：start_create_fish 與 provide_clue 對 viewer 應被拒絕
    // ------------------------------------------------------------------

    public function test_viewer_cannot_start_create_fish(): void
    {
        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback($this->makePostbackEvent('action=start_create_fish'));

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());
    }

    public function test_viewer_cannot_provide_clue(): void
    {
        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback($this->makePostbackEvent('action=provide_clue'));

        $this->assertCount(1, $replied);
        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());
    }

    // ------------------------------------------------------------------
    // 新增保護：start_rename 對 viewer 應被拒絕（目前後端無保護 → Red）
    // ------------------------------------------------------------------

    public function test_viewer_cannot_start_rename(): void
    {
        $fish = Fish::factory()->create(['name' => '原始魚名']);

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_rename&fish_id={$fish->id}")
        );

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());

        // 確認 Cache 沒有被設定（流程被阻擋）
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_renaming_fish'));
    }

    public function test_editor_can_start_rename(): void
    {
        $fish = Fish::factory()->create(['name' => '原始魚名']);

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('editor');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_rename&fish_id={$fish->id}")
        );

        // editor 可以進入修改流程（收到「請輸入新的魚類名稱：」提示）
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('請輸入新的魚類名稱', $replied[0]->getText());

        // Cache 應被設定
        $this->assertEquals(
            (string) $fish->id,
            Cache::get('line_user_' . self::USER_ID . '_renaming_fish')
        );
    }

    public function test_admin_can_start_rename(): void
    {
        $fish = Fish::factory()->create(['name' => '原始魚名']);

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('admin');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_rename&fish_id={$fish->id}")
        );

        $this->assertStringContainsString('請輸入新的魚類名稱', $replied[0]->getText());
    }

    // ------------------------------------------------------------------
    // 新增保護：start_add_audio 對 viewer 應被拒絕（目前後端無保護 → Red）
    // ------------------------------------------------------------------

    public function test_viewer_cannot_start_add_audio(): void
    {
        $fish = Fish::factory()->create();

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_add_audio&fish_id={$fish->id}")
        );

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());

        // 確認 Cache 沒有被設定
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_pending_audio_fish'));
    }

    public function test_editor_can_start_add_audio(): void
    {
        $fish = Fish::factory()->create();

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('editor');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_add_audio&fish_id={$fish->id}")
        );

        // editor 可以進入流程（收到選擇部落的提示）
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('提供發音', $replied[0]->getText());
    }

    public function test_admin_can_start_add_audio(): void
    {
        $fish = Fish::factory()->create();

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('admin');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_add_audio&fish_id={$fish->id}")
        );

        $this->assertStringContainsString('提供發音', $replied[0]->getText());
    }

    // ------------------------------------------------------------------
    // 邊界案例：即使 Cache 中有舊的 renaming 狀態，viewer 仍無法觸發
    // ------------------------------------------------------------------

    public function test_viewer_with_stale_cache_cannot_rename(): void
    {
        $fish = Fish::factory()->create(['name' => '已有名字的魚']);

        // 模擬：之前以 editor 身份操作過，但 Cache 未清除
        Cache::put('line_user_' . self::USER_ID . '_renaming_fish', $fish->id, now()->addMinutes(5));

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->andReturnUsing(function ($token, $messages) use (&$replied) {
                $replied = $messages;
            });

        $this->invokeHandlePostback(
            $this->makePostbackEvent("action=start_rename&fish_id={$fish->id}")
        );

        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());
    }
}
