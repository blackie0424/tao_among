<?php

use App\Contracts\FishServiceInterface;
use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\Fish;
use App\Models\FishNote;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LineFishKnowledgeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected const USER_ID = 'line_fish_knowledge_user';

    protected const REPLY_TOKEN = 'line_fish_knowledge_reply_token';

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
            'fish_options.note_types' => ['一般知識', '生態習性', '營養價值', '烹飪方法', '文化意義', '其他'],
        ]);

        Cache::flush();

        $this->mockLineBotService = \Mockery::mock(LineMessagingClientInterface::class);
        $this->mockLineBotService
            ->shouldReceive('getUserProfile')
            ->andReturn(['displayName' => 'Test User', 'pictureUrl' => null])
            ->byDefault();

        $this->mockLineUserService = \Mockery::mock(LineUserServiceInterface::class);
        $this->mockLineUserService
            ->shouldReceive('upsert')
            ->andReturn(new \App\Models\User)
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
            $this->app->make(FishServiceInterface::class),
        );
    }

    private function makePostbackEvent(string $data): object
    {
        $source = new class(self::USER_ID)
        {
            public function __construct(private string $userId) {}

            public function getUserId(): string
            {
                return $this->userId;
            }
        };

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
            public function __construct(private $source, private $postback) {}

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

    public function test_viewer_cannot_start_add_knowledge(): void
    {
        $fish = Fish::factory()->create();

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = $this->captureSingleReply(function () use ($fish) {
            $this->invokeHandlePostback(
                $this->makePostbackEvent("action=start_add_knowledge&fish_id={$fish->id}")
            );
        });

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('沒有此功能的使用權限', $replied[0]->getText());
        $this->assertNull(Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
    }

    public function test_start_add_knowledge_replies_with_locate_flex_card(): void
    {
        $fish = Fish::factory()->create();

        $replied = $this->captureSingleReply(function () use ($fish) {
            $this->invokeHandlePostback(
                $this->makePostbackEvent("action=start_add_knowledge&fish_id={$fish->id}")
            );
        });

        $this->assertSame('waiting_locate_selection', Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
        $this->assertSame(['fish_id' => $fish->id], Cache::get('line_user_'.self::USER_ID.'_knowledge_form'));
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);
        $labels = $this->allButtonLabels($json['contents']);

        $this->assertContains('這項知識是從哪一個部落採集到的？', $texts);
        $this->assertContains('Ivalino', $labels);
        $this->assertContains('Iranmeilek', $labels);
        $this->assertContains('❌ 取消', $labels);
    }

    public function test_selecting_locate_replies_with_note_type_flex_card(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_'.self::USER_ID.'_knowledge_state', 'waiting_locate_selection', now()->addMinutes(10));
        Cache::put('line_user_'.self::USER_ID.'_knowledge_form', ['fish_id' => $fish->id], now()->addMinutes(10));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback(
                $this->makePostbackEvent('action=select_knowledge_locate&locate=ivalino')
            );
        });

        $this->assertSame('waiting_note_type_selection', Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
        $this->assertSame([
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
        ], Cache::get('line_user_'.self::USER_ID.'_knowledge_form'));
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);
        $labels = $this->allButtonLabels($json['contents']);

        $this->assertContains('這項知識是從屬於哪一個分類項目？', $texts);
        $this->assertContains('一般知識', $labels);
        $this->assertContains('生態習性', $labels);
        $this->assertContains('❌ 取消', $labels);
    }

    public function test_selecting_note_type_prompts_for_note_content(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_'.self::USER_ID.'_knowledge_state', 'waiting_note_type_selection', now()->addMinutes(10));
        Cache::put('line_user_'.self::USER_ID.'_knowledge_form', [
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
        ], now()->addMinutes(10));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback(
                $this->makePostbackEvent('action=select_knowledge_note_type&note_type=文化意義')
            );
        });

        $this->assertSame('waiting_note_input', Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
        $this->assertSame([
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
            'note_type' => '文化意義',
        ], Cache::get('line_user_'.self::USER_ID.'_knowledge_form'));
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('請輸入進階知識內容', $replied[0]->getText());
    }

    public function test_note_input_creates_fish_note_after_locate_and_note_type(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_'.self::USER_ID.'_knowledge_state', 'waiting_note_input', now()->addMinutes(10));
        Cache::put('line_user_'.self::USER_ID.'_knowledge_form', [
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
            'note_type' => '文化意義',
        ], now()->addMinutes(10));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandleTextMessage($this->makeTextMessageEvent('祭典時會分享這種魚的故事'));
        });

        $this->assertDatabaseHas('fish_notes', [
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
            'note_type' => '文化意義',
            'note' => '祭典時會分享這種魚的故事',
        ]);
        $this->assertNull(Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
        $this->assertNull(Cache::get('line_user_'.self::USER_ID.'_knowledge_form'));
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('成功新增進階知識', $replied[0]->getText());
    }

    public function test_cancel_add_knowledge_clears_flow_state(): void
    {
        $fish = Fish::factory()->create();

        Cache::put('line_user_'.self::USER_ID.'_knowledge_state', 'waiting_note_type_selection', now()->addMinutes(10));
        Cache::put('line_user_'.self::USER_ID.'_knowledge_form', [
            'fish_id' => $fish->id,
            'locate' => 'ivalino',
        ], now()->addMinutes(10));

        $replied = $this->captureSingleReply(function () {
            $this->invokeHandlePostback($this->makePostbackEvent('action=cancel_add_knowledge'));
        });

        $this->assertNull(Cache::get('line_user_'.self::USER_ID.'_knowledge_state'));
        $this->assertNull(Cache::get('line_user_'.self::USER_ID.'_knowledge_form'));
        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('已取消新增進階知識', $replied[0]->getText());
    }

    public function test_browse_knowledge_replies_with_flex_card_for_viewer(): void
    {
        $fish = Fish::factory()->create(['name' => '飛魚']);
        FishNote::factory()->create([
            'fish_id' => $fish->id,
            'note_type' => '生態習性',
            'locate' => 'ivalino',
            'note' => '常出現在近岸海域',
        ]);
        FishNote::factory()->create([
            'fish_id' => $fish->id,
            'note_type' => '文化意義',
            'locate' => 'yayo',
            'note' => '祭典中會分享牠的故事',
        ]);

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = $this->captureSingleReply(function () use ($fish) {
            $this->invokeHandlePostback(
                $this->makePostbackEvent("action=browse_knowledge&fish_id={$fish->id}")
            );
        });

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\FlexMessage::class, $replied[0]);

        $json = $this->flexToArray($replied[0]);
        $texts = $this->flattenTexts($json['contents']);

        $this->assertContains('📚 飛魚', $texts);
        $this->assertContains('生態習性｜Ivalino', $texts);
        $this->assertContains('文化意義｜Yayo', $texts);
        $this->assertContains('常出現在近岸海域', $texts);
        $this->assertContains('祭典中會分享牠的故事', $texts);
    }

    public function test_browse_knowledge_replies_with_empty_state_when_no_note(): void
    {
        $fish = Fish::factory()->create(['name' => '鬼頭刀']);

        $this->mockLineUserService->shouldReceive('getRole')->once()->andReturn('viewer');

        $replied = $this->captureSingleReply(function () use ($fish) {
            $this->invokeHandlePostback(
                $this->makePostbackEvent("action=browse_knowledge&fish_id={$fish->id}")
            );
        });

        $this->assertCount(1, $replied);
        $this->assertInstanceOf(\LINE\Clients\MessagingApi\Model\TextMessage::class, $replied[0]);
        $this->assertStringContainsString('目前還沒有進階知識', $replied[0]->getText());
        $this->assertStringContainsString('鬼頭刀', $replied[0]->getText());
    }
}
