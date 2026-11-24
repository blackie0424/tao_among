<?php

use App\Contracts\LineMessagingClientInterface;
use App\Contracts\LineUserServiceInterface;
use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use App\Services\LineUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const LINE_NAME_TEST_USER = 'U_name_test';

/** 以 LINE 使用者（editor）依序送出 postback 或語音，收集每次的回覆。 */
class LineAudioFlowDriver
{
    public array $replies = [];

    public ?string $uploadedAs = null;

    public int $uploads = 0;

    public ?Closure $duringUpload = null;

    private LineBotController $controller;

    public function __construct(private string $userId = LINE_NAME_TEST_USER)
    {
        config(['fish_options.tribes' => ['iraraley', 'imowrod', 'ivalino', 'iranmeilek', 'iratay', 'yayo']]);
        Storage::fake('s3');
        $client = Mockery::mock(LineMessagingClientInterface::class);
        $client->shouldReceive('getUserProfile')->andReturn(['displayName' => 'T', 'pictureUrl' => null]);
        $client->shouldReceive('replyMessage')->andReturnUsing(function ($token, $messages) {
            $this->replies[] = $messages[0];
        });
        $client->shouldReceive('getMessageContent')->andReturn("\x00\x00\x00\x00ftypM4A ".str_repeat("\x00", 100));
        $users = Mockery::mock(LineUserServiceInterface::class);
        $users->shouldReceive('getRole')->andReturn('editor');
        $users->shouldReceive('upsert')->andReturn(new User);
        $upload = Mockery::mock(LineUploadService::class);
        $upload->shouldReceive('uploadLineAudio')->andReturnUsing(function () {
            $this->uploads++;
            ($this->duringUpload)?->call($this);

            return $this->uploadedAs ??= 'line-name-test.m4a';
        });
        app()->instance(LineUploadService::class, $upload);
        $this->controller = new LineBotController(
            $client,
            app(ApiFishController::class),
            app(App\Services\UploadService::class),
            app(App\Contracts\StorageServiceInterface::class),
            $users,
            app(App\Contracts\FishServiceInterface::class),
        );
    }

    public function postback(string $data): static
    {
        $event = new class($this->userId, $data)
        {
            public function __construct(private string $userId, private string $data) {}

            public function getSource(): object
            {
                return new class($this->userId)
                {
                    public function __construct(private string $id) {}

                    public function getUserId(): string
                    {
                        return $this->id;
                    }
                };
            }

            public function getPostback(): object
            {
                return new class($this->data)
                {
                    public function __construct(private string $data) {}

                    public function getData(): string
                    {
                        return $this->data;
                    }
                };
            }
        };
        $this->call('handlePostback', [$event, 'reply_token']);

        return $this;
    }

    /** 送出一段 2 秒的語音。 */
    public function audio(): static
    {
        $source = Mockery::mock(\LINE\Webhook\Model\UserSource::class);
        $source->shouldReceive('getUserId')->andReturn($this->userId);
        $message = Mockery::mock(\LINE\Webhook\Model\AudioMessageContent::class);
        $message->shouldReceive('getId')->andReturn('m1');
        $message->shouldReceive('getDuration')->andReturn(2000);
        $event = Mockery::mock(\LINE\Webhook\Model\MessageEvent::class);
        $event->shouldReceive('getSource')->andReturn($source);
        $event->shouldReceive('getMessage')->andReturn($message);
        $this->call('handleAudioMessage', [$event, 'reply_token']);

        return $this;
    }

    public function last(): string
    {
        return end($this->replies)->getText();
    }

    /** 最後一則回覆的快速回覆按鈕：[['label' => ..., 'data' => ...]] */
    public function buttons(): array
    {
        $quickReply = end($this->replies)->getQuickReply();
        $items = is_array($quickReply) ? $quickReply['items'] : $quickReply->getItems();

        return array_map(function ($item) {
            $action = is_array($item) ? $item['action'] : $item->getAction();

            return is_array($action)
                ? ['label' => $action['label'], 'data' => $action['data']]
                : ['label' => $action->getLabel(), 'data' => $action->getData()];
        }, $items);
    }

    private function call(string $method, array $args): void
    {
        $reflection = (new ReflectionClass($this->controller))->getMethod($method);
        $reflection->setAccessible(true);
        $reflection->invoke($this->controller, ...$args);
    }
}

function lineFishNamed(string $name): Fish
{
    return Fish::factory()->create(['name' => $name, 'audio_filename' => null]);
}

beforeEach(fn () => Cache::flush());

describe('多名稱的魚先選拼字（#176 AC 7）', function () {
    it('列出「序號. 完整拼字」並附按鈕，選拼字之前不問部落', function () {
        $fish = lineFishNamed('甲、乙');

        $line = (new LineAudioFlowDriver)->postback("action=start_add_audio&fish_id={$fish->id}");

        expect($line->last())->toContain("1. 甲\n2. 乙")->toContain('新增發音')->not->toContain('提供發音');
        expect($line->buttons())->toBe([
            ['label' => '1. 甲', 'data' => "action=select_name_for_audio&fish_id={$fish->id}&idx=0"],
            ['label' => '2. 乙', 'data' => "action=select_name_for_audio&fish_id={$fish->id}&idx=1"],
        ]);
    });

    it('選完拼字才選部落、再錄音；錄音存入所選拼字，完成回覆附管理發音頁網址', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;

        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=1");
        expect(collect($line->buttons())->pluck('data')->all())->each->toContain('action=select_tribe_for_audio');

        $line->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        expect($line->last())->toContain('乙')->toContain('Yayo');

        $line->audio();

        $audio = FishAudio::where('fish_id', $fish->id)->firstOrFail();
        expect($audio->only(['spoken_name', 'locate']))->toBe(['spoken_name' => '乙', 'locate' => 'yayo']);
        expect($line->last())->toContain('已成功新增')->toEndWith(url("/fish/{$fish->id}/audio-list"));
    });

    it('單一名稱的魚略過選拼字，直接選部落，名稱自動帶入', function () {
        $fish = lineFishNamed('Angsa');
        $line = new LineAudioFlowDriver;

        $line->postback("action=start_add_audio&fish_id={$fish->id}");
        expect(collect($line->buttons())->pluck('data')->all())->each->toContain('action=select_tribe_for_audio');
        expect($line->last())->toContain('新增發音')->not->toContain('提供發音');

        $line->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=iratay")->audio();

        expect(FishAudio::where('fish_id', $fish->id)->value('spoken_name'))->toBe('Angsa');
        expect($line->last())->toEndWith(url("/fish/{$fish->id}/audio-list"));
    });

    it('「重新錄製」沿用已選的拼字與部落', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=0")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=imowrod")
            ->postback("action=retry_audio&fish_id={$fish->id}")
            ->audio();

        expect(FishAudio::where('fish_id', $fish->id)->first()->only(['spoken_name', 'locate']))
            ->toBe(['spoken_name' => '甲', 'locate' => 'imowrod']);
    });

    it('兩個拼字前 20 字相同：按鈕靠序號分辨、文字不超過 20 字，訊息列完整拼字', function () {
        $prefix = str_repeat('魚', 22);
        $fish = lineFishNamed("{$prefix}甲、{$prefix}乙");

        $line = (new LineAudioFlowDriver)->postback("action=start_add_audio&fish_id={$fish->id}");

        $labels = array_column($line->buttons(), 'label');
        expect($labels)->toHaveCount(2)->and($labels[0])->not->toBe($labels[1]);
        expect($labels[0])->toStartWith('1. ')->and($labels[1])->toStartWith('2. ');
        foreach ($labels as $label) {
            expect(mb_strlen($label))->toBeLessThanOrEqual(20);
        }
        expect($line->last())->toContain("1. {$prefix}甲")->toContain("2. {$prefix}乙");
    });
});

describe('流程狀態與邊界（#176 AC 8）', function () {
    it('8a 選單顯示後魚被改名或重排：按舊按鈕被拒絕，不記任何選擇', function (string $newName) {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}");
        $fish->update(['name' => $newName]);

        $line->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=0");

        expect($line->last())->toContain('名稱已變更，請重新操作');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_pending_audio_fish'))->toBeNull();
        $line->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        expect($line->last())->toContain('已過期');
    })->with(['重排' => '乙、甲', '插入' => '丙、甲、乙', '刪除' => '甲', '改字' => '甲、丁']);

    it('8b 交錯操作兩隻魚（即使有相同拼字）：舊魚的按鈕一律過期，新魚的流程不受影響', function () {
        $fishA = lineFishNamed('甲、乙');
        $fishB = lineFishNamed('甲、丙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fishA->id}")
            ->postback("action=start_add_audio&fish_id={$fishB->id}");

        $line->postback("action=select_name_for_audio&fish_id={$fishA->id}&idx=0");
        expect($line->last())->toContain('這個按鈕已過期，請重新操作');
        $line->postback("action=select_tribe_for_audio&fish_id={$fishA->id}&tribe=yayo");
        expect($line->last())->toContain('這個按鈕已過期，請重新操作');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_adding_audio'))->toBeNull();

        $line->postback("action=select_name_for_audio&fish_id={$fishB->id}&idx=0")
            ->postback("action=select_tribe_for_audio&fish_id={$fishB->id}&tribe=yayo")
            ->audio();
        expect(FishAudio::where('fish_id', $fishB->id)->value('spoken_name'))->toBe('甲');
        expect(FishAudio::where('fish_id', $fishA->id)->count())->toBe(0);
    });

    it('8b 沒有流程暫存（逾時或沒按過「新增發音」）：選拼字、選部落、重新錄製都過期', function (string $data) {
        $fish = lineFishNamed('甲、乙');

        $line = (new LineAudioFlowDriver)->postback(str_replace('{id}', (string) $fish->id, $data));

        expect($line->last())->toContain('這個按鈕已過期，請重新操作');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_adding_audio'))->toBeNull();
    })->with([
        '選拼字' => 'action=select_name_for_audio&fish_id={id}&idx=0',
        '選部落' => 'action=select_tribe_for_audio&fish_id={id}&tribe=yayo',
        '重新錄製' => 'action=retry_audio&fish_id={id}',
    ]);

    it('8b 階段不對：沒選拼字就選部落、沒選部落就重新錄製都被拒絕', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}");

        $line->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        expect($line->last())->toContain('已過期');
        $line->postback("action=retry_audio&fish_id={$fish->id}");
        expect($line->last())->toContain('已過期');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_adding_audio'))->toBeNull();
    });

    it('8c 魚名為空：開始時就提示，不進流程', function () {
        $fish = lineFishNamed('');

        $line = (new LineAudioFlowDriver)->postback("action=start_add_audio&fish_id={$fish->id}");

        expect($line->last())->toContain('這隻魚還沒有名稱，請先到網站編輯名稱再新增發音。');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_pending_audio_fish'))->toBeNull();
    });

    it('8d 上傳前發現所選拼字已不在魚名內：不上傳、不寫資料', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=1")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        $fish->update(['name' => '甲、丙']);

        $line->audio();

        expect($line->last())->toContain('名稱已變更，請重新操作');
        expect($line->uploads)->toBe(0);
        expect(FishAudio::count())->toBe(0);
    });

    it('8d 上傳後寫入時才發現已改名：不寫資料、刪除剛上傳的檔，回「名稱已變更」', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->duringUpload = fn () => $fish->update(['name' => '甲、丙']);
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=1")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        $folder = app(App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        Storage::disk('s3')->put("{$folder}/line-name-test.m4a", 'x');

        $line->audio();

        expect($line->uploads)->toBe(1);
        expect($line->last())->toContain('名稱已變更，請重新操作');
        expect(FishAudio::count())->toBe(0);
        Storage::disk('s3')->assertMissing("{$folder}/line-name-test.m4a");
    });

    it('8e 拼字超過 13 個：提示請到網站新增，不進流程；剛好 13 個可以選', function () {
        $tooMany = lineFishNamed(implode('、', range(1, 14)));
        $line = (new LineAudioFlowDriver)->postback("action=start_add_audio&fish_id={$tooMany->id}");
        expect($line->last())->toContain('請到網站新增');
        expect(Cache::get('line_user_'.LINE_NAME_TEST_USER.'_pending_audio_fish'))->toBeNull();

        $thirteen = lineFishNamed(implode('、', range(1, 13)));
        $line->postback("action=start_add_audio&fish_id={$thirteen->id}");
        expect($line->buttons())->toHaveCount(13);
    });

    it('8d 單一名稱的魚開始時就綁定拼字：上傳前改名，不上傳、不寫資料，不會以新名稱存檔', function () {
        $fish = lineFishNamed('甲');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        $fish->update(['name' => '乙']);

        $line->audio();

        expect($line->last())->toContain('名稱已變更，請重新操作');
        expect($line->uploads)->toBe(0);
        expect(FishAudio::count())->toBe(0);
    });

    it('8d 單一名稱的魚上傳後才改名：不寫資料、刪除剛上傳的檔，回「名稱已變更」', function () {
        $fish = lineFishNamed('甲');
        $line = new LineAudioFlowDriver;
        $line->duringUpload = fn () => $fish->update(['name' => '乙']);
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");
        $folder = app(App\Contracts\StorageServiceInterface::class)->getAudioFolder();
        Storage::disk('s3')->put("{$folder}/line-name-test.m4a", 'x');

        $line->audio();

        expect($line->uploads)->toBe(1);
        expect($line->last())->toContain('名稱已變更，請重新操作');
        expect(FishAudio::count())->toBe(0);
        Storage::disk('s3')->assertMissing("{$folder}/line-name-test.m4a");
    });

    it('8b 重新按「新增發音」遇到空名或拼字太多的魚：舊流程一併清除，之後的錄音不會存到前一隻魚', function (string $nextName) {
        $fishA = lineFishNamed('甲、乙');
        $fishB = lineFishNamed($nextName);
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fishA->id}")
            ->postback("action=select_name_for_audio&fish_id={$fishA->id}&idx=0")
            ->postback("action=select_tribe_for_audio&fish_id={$fishA->id}&tribe=yayo");

        $line->postback("action=start_add_audio&fish_id={$fishB->id}")->audio();

        expect($line->last())->toContain('請先點擊');
        expect($line->uploads)->toBe(0);
        expect(FishAudio::count())->toBe(0);
    })->with(['空名' => '', '拼字太多' => implode('、', range(1, 14))]);

    it('8b 已選過拼字後重按舊選單的另一個拼字：過期，不改寫已選的拼字，錄音仍存原本選的', function () {
        $fish = lineFishNamed('甲、乙');
        $line = new LineAudioFlowDriver;
        $line->postback("action=start_add_audio&fish_id={$fish->id}")
            ->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=0")
            ->postback("action=select_tribe_for_audio&fish_id={$fish->id}&tribe=yayo");

        $line->postback("action=select_name_for_audio&fish_id={$fish->id}&idx=1");
        expect($line->last())->toContain('這個按鈕已過期，請重新操作');

        $line->audio();
        expect(FishAudio::where('fish_id', $fish->id)->value('spoken_name'))->toBe('甲');
    });

    it('多名稱的魚沒選拼字就有錄音（舊暫存）：不上傳、不寫資料，請重新操作', function () {
        $fish = lineFishNamed('甲、乙');
        Cache::put('line_user_'.LINE_NAME_TEST_USER.'_audio_tribe', 'yayo', now()->addMinutes(5));
        Cache::put('line_user_'.LINE_NAME_TEST_USER.'_adding_audio', $fish->id, now()->addMinutes(5));

        $line = (new LineAudioFlowDriver)->audio();

        expect($line->last())->toContain('這個按鈕已過期，請重新操作');
        expect($line->uploads)->toBe(0);
        expect(FishAudio::count())->toBe(0);
    });
});
