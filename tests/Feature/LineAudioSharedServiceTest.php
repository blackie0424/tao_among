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

uses(RefreshDatabase::class);

const LINE_AUDIO_BLOB_PREFIX = "\x00\x00\x00\x00ftypM4A ";

/** 以 saveFishAudio 儲存一段 LINE 錄音；回傳 LINE 回覆的文字。 */
function saveLineAudio(string $lineUserId, Fish $fish, ?string $tribe, string $filename = 'line-new.m4a', int $duration = 3000): ?string
{
    $blob = LINE_AUDIO_BLOB_PREFIX.str_repeat("\x00", 100);
    $upload = Mockery::mock(LineUploadService::class);
    $upload->shouldReceive('uploadLineAudio')->andReturn($filename)->byDefault();
    app()->instance(LineUploadService::class, $upload);

    if ($tribe) {
        Cache::put("line_user_{$lineUserId}_audio_tribe", $tribe, now()->addMinutes(5));
    }
    Cache::put("line_user_{$lineUserId}_adding_audio", $fish->id, now()->addMinutes(5));

    $reply = null;
    $client = Mockery::mock(LineMessagingClientInterface::class);
    $client->shouldReceive('replyMessage')->andReturnUsing(function ($token, $messages) use (&$reply) {
        $reply = $messages[0]->getText();
    });

    $controller = new LineBotController(
        $client,
        app(ApiFishController::class),
        app(App\Services\UploadService::class),
        app(App\Contracts\StorageServiceInterface::class),
        Mockery::mock(LineUserServiceInterface::class),
        app(App\Contracts\FishServiceInterface::class),
    );
    $method = (new ReflectionClass($controller))->getMethod('saveFishAudio');
    $method->setAccessible(true);
    $method->invoke($controller, $lineUserId, $fish->id, $blob, $duration, 'reply_token');

    return $reply;
}

it('LINE 新增發音：單一名稱的魚自動帶入名稱，部落與錄音者都記下', function () {
    $fish = Fish::factory()->create(['name' => 'Angsa', 'audio_filename' => null]);
    $user = User::factory()->create(['line_user_id' => 'U_line_1']);

    saveLineAudio('U_line_1', $fish, 'iratay');

    $audio = FishAudio::where('fish_id', $fish->id)->firstOrFail();
    expect($audio->only(['name', 'locate', 'spoken_name', 'duration']))
        ->toBe(['name' => 'line-new.m4a', 'locate' => 'iratay', 'spoken_name' => 'Angsa', 'duration' => 3000]);
    expect($audio->makeVisible('recorded_by')->recorded_by)->toBe($user->id);
});

it('LINE 新增發音：多名稱的魚在選名稱流程上線前名稱留空（歸未對應名稱）；查無帳號時錄音者為空', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);

    saveLineAudio('U_unknown_user', $fish, 'yayo');

    $audio = FishAudio::where('fish_id', $fish->id)->firstOrFail();
    expect($audio->spoken_name)->toBeNull();
    expect($audio->makeVisible('recorded_by')->recorded_by)->toBeNull();
});

it('LINE 新增發音不再蓋掉舊錄音：同名稱同部落再錄是新增一筆', function () {
    $fish = Fish::factory()->create(['name' => 'Angsa']);
    FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo', 'spoken_name' => 'Angsa']);

    $reply = saveLineAudio('U_line_2', $fish, 'yayo');

    expect(FishAudio::where('fish_id', $fish->id)->pluck('name')->sort()->values()->all())->toBe(['line-new.m4a', 'old.m4a']);
    expect($reply)->toContain('再錄一次會新增另一段，不會蓋掉舊的')->not->toContain('覆蓋');
});

it('LINE 沒有選到可用的部落時不上傳、不寫資料，並請使用者重新操作', function (?string $tribe) {
    $fish = Fish::factory()->create(['name' => 'Angsa']);

    $reply = saveLineAudio('U_line_3', $fish, $tribe);

    expect($reply)->toContain('部落資料無效');
    expect(FishAudio::count())->toBe(0);
    expect(Cache::get('line_user_U_line_3_adding_audio'))->toBeNull();
})->with([null, 'unknown', 'supabase']);

it('LINE 魚名為空時提示先編輯名稱，不上傳、不寫資料', function () {
    $fish = Fish::factory()->create(['name' => '']);

    $reply = saveLineAudio('U_line_4', $fish, 'yayo');

    expect($reply)->toContain('還沒有名稱');
    expect(FishAudio::count())->toBe(0);
});
