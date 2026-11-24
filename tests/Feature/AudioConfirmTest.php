<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use App\Services\AudioConfirmService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const AUDIO_CONFIRM_PENDING_NAME = '11111111-2222-3333-4444-555555555555.m4a';
const AUDIO_CONFIRM_FINAL_PATH = '#^audios/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$#';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->storage = $this->mock(StorageServiceInterface::class)->shouldIgnoreMissing();
    $this->storage->shouldReceive('getAudioFolder')->andReturn('audios');
    $this->copied = []; // 每次複製到正式資料夾的目的路徑
    $this->deleted = [];
    $this->storage->shouldReceive('copyObject')->andReturnUsing(function ($from, $to) {
        $this->copied[] = $to;

        return true;
    })->byDefault();
    $this->storage->shouldReceive('delete')->andReturnUsing(function ($path) {
        $this->deleted[] = $path;

        return true;
    })->byDefault();
});

function pendingExists($test, int $size = 40_000): void
{
    $test->storage->shouldReceive('fileSize')->with(FishAudio::pendingPath($test->user->id, AUDIO_CONFIRM_PENDING_NAME))->andReturn($size);
}

function confirmBody(array $override = []): array
{
    return array_merge(['filename' => AUDIO_CONFIRM_PENDING_NAME, 'spoken_name' => 'mivazlt', 'tribe' => 'yayo', 'duration_ms' => 4200], $override);
}

function postConfirm($test, Fish $fish, array $override = [])
{
    return $test->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody($override));
}

it('確認後複製成新的正式檔名並新增一筆：部落、名稱、長度、錄音者都記下，主要發音照現況指向新檔', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang', 'audio_filename' => null]);
    pendingExists($this);

    postConfirm($this, $fish)->assertStatus(201);

    $audio = FishAudio::where('fish_id', $fish->id)->firstOrFail();
    expect($this->copied)->toHaveCount(1)->and($this->copied[0])->toMatch(AUDIO_CONFIRM_FINAL_PATH)->toBe('audios/'.$audio->name);
    expect($audio->only(['locate', 'spoken_name', 'duration']))->toBe(['locate' => 'yayo', 'spoken_name' => 'mivazlt', 'duration' => 4200]);
    expect($audio->makeVisible('recorded_by')->recorded_by)->toBe($this->user->id);
    expect($fish->fresh()->audio_filename)->toBe($audio->name);
});

it('同名稱同部落再錄一段是新增一筆，舊錄音與舊檔都在', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    $old = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo', 'spoken_name' => 'mivazlt']);
    pendingExists($this);

    postConfirm($this, $fish)->assertStatus(201);

    expect(FishAudio::where('fish_id', $fish->id)->count())->toBe(2);
    expect($old->fresh()->name)->toBe('old.m4a');
    expect($this->deleted)->toBe([]);
});

it('同一個暫存檔重送確認不會產生兩筆，也不會再複製', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);

    $first = postConfirm($this, $fish);
    $second = postConfirm($this, $fish);

    $first->assertStatus(201);
    $second->assertStatus(201);
    expect($second->json('id'))->toBe($first->json('id'));
    expect(FishAudio::count())->toBe(1);
    expect($this->copied)->toHaveCount(1);
});

it('新增暫存檔 A → 換成 B → 重送 A：不會產生第二筆；別隻魚也不能再用 A', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    $other = Fish::factory()->create(['name' => 'Angsa']);
    $pendingB = '99999999-8888-7777-6666-555555555555.m4a';
    pendingExists($this);
    $this->storage->shouldReceive('fileSize')->with(FishAudio::pendingPath($this->user->id, $pendingB))->andReturn(40_000);

    $created = postConfirm($this, $fish)->assertStatus(201);
    $audio = FishAudio::findOrFail($created->json('id'));
    app(AudioConfirmService::class)->replace($audio, $this->user->id, $pendingB, 3000, 'yayo');
    $copiedBefore = count($this->copied);

    postConfirm($this, $fish)->assertStatus(201)->assertJsonPath('id', $audio->id); // 同魚重送 A：回傳同一筆
    postConfirm($this, $other, ['spoken_name' => 'Angsa'])->assertStatus(422)->assertJsonValidationErrors(['recording']);

    expect(FishAudio::count())->toBe(1);
    expect($this->copied)->toHaveCount($copiedBefore); // 沒有再複製
});

it('並發重送：搶輸的請求刪掉自己複製的檔，搶贏的正式檔不受影響', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    $raced = false;
    $this->storage->shouldReceive('copyObject')->andReturnUsing(function ($from, $to) use ($fish, &$raced) {
        $this->copied[] = $to;
        if (! $raced) {
            $raced = true; // 第一個請求複製完、還沒寫資料庫時，第二個相同的確認先完成了
            app(AudioConfirmService::class)->confirm($fish, $this->user->id, AUDIO_CONFIRM_PENDING_NAME, 'mivazlt', 'yayo', 4200);
        }

        return true;
    });

    $response = postConfirm($this, $fish);

    $response->assertStatus(201);
    $winner = FishAudio::firstOrFail();
    expect(FishAudio::count())->toBe(1);
    expect($response->json('id'))->toBe($winner->id);
    expect($this->copied)->toHaveCount(2);
    expect($this->deleted)->toBe([$this->copied[0]]); // 只刪自己（輸的）複製的檔
    expect($this->copied[1])->toBe('audios/'.$winner->name); // 贏的檔還在
});

it('一個請求失敗、另一個成功的交錯：失敗的只刪自己的檔，成功的檔與紀錄都在', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    $raced = false;
    $this->storage->shouldReceive('copyObject')->andReturnUsing(function ($from, $to) use ($fish, &$raced) {
        $this->copied[] = $to;
        if (! $raced) {
            $raced = true;
            app(AudioConfirmService::class)->confirm($fish, $this->user->id, AUDIO_CONFIRM_PENDING_NAME, 'mivazlt', 'yayo', 4200);
            // 這之後輪到第一個請求寫資料庫，並且遇到非唯一鍵的失敗
            FishAudio::creating(fn () => throw new RuntimeException('寫入失敗'));
        }

        return true;
    });

    postConfirm($this, $fish)->assertStatus(500);

    $winner = FishAudio::firstOrFail();
    expect(FishAudio::count())->toBe(1);
    expect($this->deleted)->toBe([$this->copied[0]]);
    expect($this->copied[1])->toBe('audios/'.$winner->name);
});

it('同一個暫存檔不能確認到另一隻魚：直接拒絕，不複製、不留檔', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    $other = Fish::factory()->create(['name' => 'Angsa']);
    pendingExists($this);
    postConfirm($this, $fish)->assertStatus(201);

    postConfirm($this, $other, ['spoken_name' => 'Angsa'])->assertStatus(422)->assertJsonValidationErrors(['recording']);

    expect(FishAudio::count())->toBe(1);
    expect(FishAudio::first()->fish_id)->toBe($fish->id);
    expect($this->copied)->toHaveCount(1);
    expect($this->deleted)->toBe([]);
});

it('已被刪除的紀錄用過的暫存檔不能再確認：直接拒絕，不複製、不留檔', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    postConfirm($this, $fish)->assertStatus(201);
    FishAudio::first()->delete();

    postConfirm($this, $fish)->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
    expect($this->copied)->toHaveCount(1);
});

it('簽名成功但沒有 PUT（暫存檔不存在）時不產生資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
    $this->storage->shouldReceive('fileSize')->andReturn(null);

    postConfirm($this, $fish)->assertStatus(422)->assertJsonValidationErrors(['recording']);

    expect(FishAudio::count())->toBe(0);
    expect($fish->fresh()->audio_filename)->toBe('main.m4a');
});

it('暫存檔超過 2 MB 時被拒絕，不複製也不寫資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this, FishAudio::MAX_BYTES + 1);

    postConfirm($this, $fish)->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
    expect($this->copied)->toBe([]);
});

it('複製失敗時回錯誤且不寫資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->andReturn(false);

    postConfirm($this, $fish)->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
});

it('寫入資料庫失敗時回滾，並刪掉自己複製的檔，主要發音不變', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
    pendingExists($this);
    FishAudio::creating(fn () => throw new RuntimeException('寫入失敗'));

    postConfirm($this, $fish)->assertStatus(500);

    expect(FishAudio::count())->toBe(0);
    expect($this->deleted)->toBe($this->copied);
    expect($fish->fresh()->audio_filename)->toBe('main.m4a');
});

it('名稱不在這隻魚的名稱內被拒絕，且不複製', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);

    postConfirm($this, $fish, ['spoken_name' => '別的名字'])->assertStatus(422)->assertJsonValidationErrors(['spoken_name']);

    expect(FishAudio::count())->toBe(0);
    expect($this->copied)->toBe([]);
});

it('魚名為空時拒絕新增', function () {
    $fish = Fish::factory()->create(['name' => '']);
    pendingExists($this);

    postConfirm($this, $fish)->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
});

it('部落必填且須是可選的部落、長度必填且不超過 5 秒、檔名格式要對', function (array $override, string $field) {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);

    postConfirm($this, $fish, $override)->assertStatus(422)->assertJsonValidationErrors([$field]);

    expect(FishAudio::count())->toBe(0);
})->with([
    'tribe 空' => [['tribe' => ''], 'tribe'],
    'tribe supabase' => [['tribe' => 'supabase'], 'tribe'],
    'tribe unknown' => [['tribe' => 'unknown'], 'tribe'],
    '沒有長度' => [['duration_ms' => null], 'duration_ms'],
    '超過 5 秒' => [['duration_ms' => 5001], 'duration_ms'],
    '路徑穿越檔名' => [['filename' => '../x.m4a'], 'filename'],
]);

it('viewer 不能確認錄音', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);

    $this->actingAs(User::factory()->lineViewer()->create());
    postConfirm($this, $fish)->assertForbidden();
});
