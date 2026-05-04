<?php

use App\Http\Controllers\LineBotController;
use App\Http\Controllers\ApiFishController;
use App\Services\LineBotService;
use App\Services\UploadService;
use App\Contracts\StorageServiceInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\FishServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 測試「新增魚類流程」與「瀏覽魚類」之間的 Cache 狀態污染問題。
 *
 * Bug 重現路徑：
 *   1. 使用者點「新增魚類」→ Cache 設定 create_fish_state = waiting_*
 *   2. 使用者沒有取消，直接點「瀏覽資料」
 *   3. 預期：正常顯示部落選擇選單；Cache 殘留狀態被清除
 *   4. 實際（修復前）：有機率觸發新增魚類的回應
 *
 * 測試策略：
 *   - 直接操作 Cache 預置狀態，再呼叫 handlePostback（protected），驗證行為與 Cache 清理
 *   - 使用 Reflection 存取 protected 方法
 *   - Mock LineBotService::replyMessage 捕捉實際回傳的訊息內容
 */
class LineBotCacheStateTest extends TestCase
{
    use RefreshDatabase;

    protected const USER_ID = 'test_user_id_abc';
    protected const REPLY_TOKEN = 'test_reply_token';

    protected LineBotController $controller;
    protected LineBotService|\Mockery\MockInterface $mockLineBotService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['line.channel_secret' => 'test_channel_secret']);
        config(['line.channel_access_token' => 'test_access_token']);
        config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino', 'iranmeilek', 'iratay', 'yayo']]);

        // 每個測試前清除 Cache
        Cache::flush();

        // 預備 Mock
        $this->mockLineBotService = \Mockery::mock(LineBotService::class);

        // buildBrowseTribesCarousel 預設回傳一個假 FlexMessage（多數測試只驗證 Cache，不關心回應內容）
        $fakeFlexMessage = new \LINE\Clients\MessagingApi\Model\FlexMessage([
            'type'    => 'flex',
            'altText' => '魚類圖鑑共 0 筆，請選擇部落',
            'contents' => ['type' => 'carousel', 'contents' => []],
        ]);
        $this->mockLineBotService
            ->shouldReceive('buildBrowseTribesCarousel')
            ->andReturn([$fakeFlexMessage])
            ->byDefault();

        // getUserProfile 的預設回傳（upsertLineUserByProfile 內部用）
        $this->mockLineBotService
            ->shouldReceive('getUserProfile')
            ->andReturn(['displayName' => 'Test User', 'pictureUrl' => null])
            ->byDefault();

        $mockLineUserService = \Mockery::mock(LineUserServiceInterface::class);
        $mockLineUserService->shouldReceive('upsert')->andReturn(new \App\Models\User())->byDefault();
        // 預設角色為 editor，讓 Cache 狀態測試聚焦在流程邏輯而非權限
        $mockLineUserService->shouldReceive('getRole')->andReturn('editor')->byDefault();
        $this->controller = new LineBotController(
            $this->mockLineBotService,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $this->app->make(StorageServiceInterface::class),
            $mockLineUserService,
            $this->app->make(FishServiceInterface::class)
        );
    }

    // =========================================================
    // Helper：建立假的 PostbackEvent
    // =========================================================

    /**
     * 建立一個可呼叫 handlePostback 的假事件物件
     */
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

        return new class($source, $postback, self::REPLY_TOKEN) {
            public function __construct(
                private $source,
                private $postback,
                private string $replyToken,
            ) {
            }
            public function getSource()
            {
                return $this->source;
            }
            public function getPostback()
            {
                return $this->postback;
            }
            public function getReplyToken(): string
            {
                return $this->replyToken;
            }
        };
    }

    /**
     * 建立符合 LINE\Webhook\Model\MessageEvent 型別的 Mock（含 userId 與文字訊息）
     */
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

        $event = \Mockery::mock(\LINE\Webhook\Model\MessageEvent::class)
            ->shouldReceive('getSource')->andReturn($source)
            ->shouldReceive('getMessage')->andReturn($message)
            ->shouldReceive('getReplyToken')->andReturn(self::REPLY_TOKEN)
            ->getMock();

        return $event;
    }

    /**
     * 透過 Reflection 呼叫 handlePostback
     */
    private function callHandlePostback(object $event, string $replyToken): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handlePostback');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, $replyToken);
    }

    /**
     * 透過 Reflection 呼叫 handleTextMessage
     */
    private function callHandleTextMessage(\LINE\Webhook\Model\MessageEvent $event, string $replyToken): void
    {
        $method = (new \ReflectionClass($this->controller))->getMethod('handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($this->controller, $event, $replyToken);
    }

    // =========================================================
    // 情境一：waiting_image 狀態 + 點擊「瀏覽資料」
    // =========================================================

    /**
     * [主要 Bug] 使用者點「新增魚類」後（waiting_image），直接點「瀏覽資料」
     * → 應該正常顯示部落選單，並清除 create_fish_state
     */
    public function test_browse_tribes_menu_clears_waiting_image_state(): void
    {
        // 預置：使用者處於等待上傳圖片狀態
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_image', now()->addMinutes(5));

        // 預期：回覆部落選擇 FlexMessage Carousel
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(
                self::REPLY_TOKEN,
                \Mockery::on(
                    fn ($msgs) =>
                    count($msgs) === 1 &&
                    $msgs[0] instanceof \LINE\Clients\MessagingApi\Model\FlexMessage
                )
            );

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        // 驗證 Cache 已被清除
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_images'));
    }

    /**
     * 使用者點「新增魚類」、上傳圖片後（waiting_name_choice），直接點「瀏覽資料」
     * → 應該正常顯示部落選單，並清除殘留 Cache（含圖片暂存）
     */
    public function test_browse_tribes_menu_clears_waiting_name_choice_state(): void
    {
        // 預置：使用者已上傳圖片、等待名稱選擇
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_name_choice', now()->addMinutes(5));
        Cache::put('line_user_' . self::USER_ID . '_create_fish_images', ['some-image.jpg'], now()->addMinutes(5));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        // 驗證兩個 Cache 都被清除
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_images'));
    }

    /**
     * 使用者在 waiting_custom_name 狀態（已輸入要自訂名稱），直接點「瀏覽資料」
     * → 應該清除狀態，顯示部落選單
     */
    public function test_browse_tribes_menu_clears_waiting_custom_name_state(): void
    {
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_custom_name', now()->addMinutes(5));
        Cache::put('line_user_' . self::USER_ID . '_create_fish_images', ['some-image.jpg'], now()->addMinutes(5));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_images'));
    }

    // =========================================================
    // 情境二：等待圖片狀態 + 點擊特定部落瀏覽
    // =========================================================

    /**
     * 在 waiting_image 狀態直接瀏覽特定部落 (browse_tribe_data)
     * → 應清除 create_fish_state，正常顯示部落魚類
     */
    public function test_browse_tribe_data_clears_create_fish_state(): void
    {
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_image', now()->addMinutes(5));

        // handleBrowseByFilter 會呼叫 ApiFishController，這裡讓 replyMessage 接受任何輸入
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribe_data&tribe=iraraley'),
            self::REPLY_TOKEN
        );

        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_images'));
    }

    // =========================================================
    // 情境三：等待圖片狀態 + 點擊「提供線索」
    // =========================================================

    /**
     * 在 waiting_image 狀態點擊「提供線索 (provide_clue)」
     * → 應清除 create_fish_state
     */
    public function test_provide_clue_clears_create_fish_state(): void
    {
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_image', now()->addMinutes(5));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        // handleRandomUnknownFish 會呼叫 ApiFishController 查詢 random-unknown
        // 資料庫是空的，所以會回傳「目前沒有待命名的魚類」
        $this->callHandlePostback(
            $this->makePostbackEvent('action=provide_clue'),
            self::REPLY_TOKEN
        );

        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
    }

    // =========================================================
    // 情境四：等待圖片狀態 + 傳送文字訊息
    // =========================================================

    /**
     * [關鍵] 使用者在 waiting_image 狀態傳送文字
     * → 應提示傳送圖片，不應觸發 createFish 或搜尋魚類
     */
    public function test_text_message_during_waiting_image_prompts_upload(): void
    {
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_image', now()->addMinutes(5));

        $repliedMessages = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(
                self::REPLY_TOKEN,
                \Mockery::on(function ($msgs) use (&$repliedMessages) {
                    $repliedMessages = $msgs;
                    return true;
                })
            );

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('瀏覽資料 📖'), // 模擬使用者文字插入
            self::REPLY_TOKEN
        );

        // 驗證：回覆的是「請傳送圖片」提示，而非觸發魚類搜尋
        $this->assertNotEmpty($repliedMessages);
        $firstMsg = $repliedMessages[0];
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $firstMsg);
        $this->assertStringContainsString('圖片', $firstMsg->getText());

        // 驗證 waiting_image 狀態仍保留（使用者應繼續上傳）
        $this->assertEquals('waiting_image', Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));

        // 驗證沒有建立任何 Fish 記錄
        $this->assertDatabaseCount('fish', 0);
    }

    /**
     * 使用者在 waiting_name_choice 狀態傳送任意文字
     * → 應重送名稱選擇提示，不應觸發 createFish
     */
    public function test_text_message_during_waiting_name_choice_shows_options(): void
    {
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_name_choice', now()->addMinutes(5));
        Cache::put('line_user_' . self::USER_ID . '_create_fish_images', ['some-image.jpg'], now()->addMinutes(5));

        $repliedMessages = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(
                self::REPLY_TOKEN,
                \Mockery::on(function ($msgs) use (&$repliedMessages) {
                    $repliedMessages = $msgs;
                    return true;
                })
            );

        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('我是任意文字'),
            self::REPLY_TOKEN
        );

        // 驗證回覆帶有 QuickReply（名稱選擇選項）
        $firstMsg = $repliedMessages[0];
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $firstMsg);
        $quickReply = $firstMsg->getQuickReply();
        $this->assertNotNull($quickReply);

        // 驗證沒有建立 Fish 記錄
        $this->assertDatabaseCount('fish', 0);
    }

    // =========================================================
    // 情境五：waiting_custom_name 狀態 + 傳送文字（合法路徑）
    // =========================================================

    /**
     * 使用者在 waiting_custom_name 狀態輸入魚名 → 應正確建立魚類
     * （這是正常的合法流程，確保修改後仍正常運作）
     */
    public function test_text_message_during_waiting_custom_name_creates_fish(): void
    {
        // 預置：已有暫存圖片與 waiting_custom_name 狀態
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_custom_name', now()->addMinutes(5));
        Cache::put('line_user_' . self::USER_ID . '_create_fish_images', ['some-fish-image.jpg'], now()->addMinutes(5));

        // Mock StorageService 回傳圖片 URL
        $mockStorageService = \Mockery::mock(StorageServiceInterface::class);
        $mockStorageService->shouldReceive('getUrl')
            ->andReturn('https://example.com/some-fish-image.jpg');

        $mockLineUserService4 = \Mockery::mock(LineUserServiceInterface::class);
        $mockLineUserService4->shouldReceive('upsert')->andReturn(new \App\Models\User())->byDefault();
        $mockLineUserService4->shouldReceive('getRole')->andReturn('editor')->byDefault();
        $controller = new LineBotController(
            $this->mockLineBotService,
            $this->app->make(ApiFishController::class),
            $this->app->make(UploadService::class),
            $mockStorageService,
            $mockLineUserService4,
            $this->app->make(FishServiceInterface::class)
        );

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $method = (new \ReflectionClass($controller))->getMethod('handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $this->makeTextMessageEvent('黑鯛'), self::REPLY_TOKEN);

        // 驗證 Fish 被建立且名稱正確
        $this->assertDatabaseHas('fish', ['name' => '黑鯛']);

        // 驗證 Cache 已被清除
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_images'));
    }

    // =========================================================
    // 情境六：無 Cache 狀態時的正常 browse 流程
    // =========================================================

    /**
     * 沒有任何流程 Cache 的情況下點「瀏覽資料」→ 應完全正常
     */
    public function test_browse_tribes_menu_works_without_any_cache_state(): void
    {
        // 確保 Cache 完全空白
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));

        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        // 狀態依然是空的
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));
    }

    // =========================================================
    // 情境七：連續點擊驗證（模擬快速切換的 race-condition）
    // =========================================================

    /**
     * 模擬使用者快速操作：
     *   1. 點「新增魚類」 → 設定 waiting_image
     *   2. 點「瀏覽資料」 → 應清除 waiting_image，顯示部落選單
     *   3. 選擇部落 → 應正常顯示魚類列表
     *
     * 確保整個序列的 Cache 不互相污染
     */
    public function test_full_sequence_start_create_then_browse_tribe(): void
    {
        // Step 1：點「新增魚類」
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->times(3) // 三次 postback 各回覆一次
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        // start_create_fish：設定 waiting_image
        $this->callHandlePostback(
            $this->makePostbackEvent('action=start_create_fish'),
            self::REPLY_TOKEN
        );
        $this->assertEquals('waiting_image', Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));

        // Step 2：立刻點「瀏覽資料」（不取消）
        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );
        // waiting_image 必須已被清除
        $this->assertNull(
            Cache::get('line_user_' . self::USER_ID . '_create_fish_state'),
            '點擊瀏覽資料後 create_fish_state 應被清除'
        );

        // Step 3：選擇部落 iraraley
        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribe_data&tribe=iraraley'),
            self::REPLY_TOKEN
        );
        // 仍然沒有 create_fish_state
        $this->assertNull(
            Cache::get('line_user_' . self::USER_ID . '_create_fish_state'),
            '選擇部落後 create_fish_state 應仍然是空的'
        );

        // 驗證整個過程沒有建立任何 Fish 記錄
        $this->assertDatabaseCount('fish', 0);
    }

    /**
     * 模擬使用者快速操作：
     *   1. 點「新增魚類」
     *   2. 上傳圖片（Cache 設為 waiting_name_choice）
     *   3. 點「瀏覽資料」→ 應清除所有殘留 Cache
     *   4. 之後傳任何文字 → 應觸發魚類搜尋，而非建立魚類
     */
    public function test_after_browse_text_triggers_search_not_create(): void
    {
        // 預置：已上傳圖片、等待選擇名稱
        Cache::put('line_user_' . self::USER_ID . '_create_fish_state', 'waiting_name_choice', now()->addMinutes(5));
        Cache::put('line_user_' . self::USER_ID . '_create_fish_images', ['uploaded-img.jpg'], now()->addMinutes(5));

        // Step 1：點「瀏覽資料」清除狀態
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->twice() // browse + 文字搜尋
            ->with(self::REPLY_TOKEN, \Mockery::type('array'));

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        // 確認 Cache 清除
        $this->assertNull(Cache::get('line_user_' . self::USER_ID . '_create_fish_state'));

        // Step 2：傳送文字 → 應該是搜尋魚類，不是建立魚類
        $this->callHandleTextMessage(
            $this->makeTextMessageEvent('黑鯛'),
            self::REPLY_TOKEN
        );

        // 驗證沒有建立 Fish（因為搜尋結果是空的，不會建立）
        $this->assertDatabaseCount('fish', 0);
    }

    // =========================================================
    // 情境八：confirm 瀏覽 postback 回覆內容驗證
    // =========================================================

    /**
     * 點擊「瀏覽資料」回覆的是 FlexMessage Carousel（各部落一張卡片）
     * 這個測試使用真實的 LineBotService 來驗證 Flex 結構
     */
    public function test_browse_tribes_menu_reply_contains_tribe_flex_carousel(): void
    {
        // 建立一些 Fish 資料（用於計算總數）
        \App\Models\Fish::factory()->count(3)->create();

        // 覆寫 byDefault mock：讓這個測試呼叫真實的 buildBrowseTribesCarousel
        $realService = new \App\Services\LineBotService();
        $this->mockLineBotService
            ->shouldReceive('buildBrowseTribesCarousel')
            ->once()
            ->andReturnUsing(fn () => $realService->buildBrowseTribesCarousel());

        $repliedMessages = [];
        $this->mockLineBotService
            ->shouldReceive('replyMessage')
            ->once()
            ->with(
                self::REPLY_TOKEN,
                \Mockery::on(function ($msgs) use (&$repliedMessages) {
                    $repliedMessages = $msgs;
                    return true;
                })
            );

        $this->callHandlePostback(
            $this->makePostbackEvent('action=browse_tribes_menu'),
            self::REPLY_TOKEN
        );

        // 應回覆 1 則 FlexMessage
        $this->assertCount(1, $repliedMessages);
        $msg = $repliedMessages[0];
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $msg);

        // altText 應包含部落選擇文字
        $this->assertStringContainsString('請選擇部落', $msg->getAltText());

        // contents 應是 carousel 型別，且有 6 個部落 bubble
        $contents = $msg->getContents();
        $this->assertEquals('carousel', $contents['type']);
        $this->assertCount(6, $contents['contents']);
    }
}
