<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const PENDING_NAME = '11111111-2222-3333-4444-555555555555.m4a';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->storage = $this->mock(StorageServiceInterface::class)->shouldIgnoreMissing();
    $this->storage->shouldReceive('getAudioFolder')->andReturn('audios');
});

function pendingExists($test, int $size = 40_000): void
{
    $test->storage->shouldReceive('fileSize')->with(FishAudio::pendingPath($test->user->id, PENDING_NAME))->andReturn($size);
}

function confirmBody(array $override = []): array
{
    return array_merge(['filename' => PENDING_NAME, 'spoken_name' => 'mivazlt', 'tribe' => 'yayo', 'duration_ms' => 4200], $override);
}

it('確認後複製到正式資料夾並新增一筆：檔名、部落、名稱、長度、錄音者都記下，主要發音照現況指向新檔', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang', 'audio_filename' => null]);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->once()->with(FishAudio::pendingPath($this->user->id, PENDING_NAME), 'audios/'.PENDING_NAME)->andReturn(true);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(201);

    $audio = FishAudio::where('fish_id', $fish->id)->firstOrFail();
    expect($audio->only(['name', 'locate', 'spoken_name', 'duration']))->toBe(['name' => PENDING_NAME, 'locate' => 'yayo', 'spoken_name' => 'mivazlt', 'duration' => 4200]);
    expect($audio->makeVisible('recorded_by')->recorded_by)->toBe($this->user->id);
    expect($fish->fresh()->audio_filename)->toBe(PENDING_NAME);
});

it('同名稱同部落再錄一段是新增一筆，舊錄音與舊檔都在', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    $old = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo', 'spoken_name' => 'mivazlt']);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->andReturn(true);
    $this->storage->shouldNotReceive('delete');

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(201);

    expect(FishAudio::where('fish_id', $fish->id)->count())->toBe(2);
    expect($old->fresh()->name)->toBe('old.m4a');
});

it('同一個暫存檔重送確認不會產生兩筆', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->once()->andReturn(true);

    $first = $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody());
    $second = $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody());

    $first->assertStatus(201);
    $second->assertStatus(201);
    expect($second->json('id'))->toBe($first->json('id'));
    expect(FishAudio::count())->toBe(1);
});

it('簽名成功但沒有 PUT（暫存檔不存在）時不產生資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
    $this->storage->shouldReceive('fileSize')->andReturn(null);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(422)->assertJsonValidationErrors(['recording']);

    expect(FishAudio::count())->toBe(0);
    expect($fish->fresh()->audio_filename)->toBe('main.m4a');
});

it('暫存檔超過 2 MB 時被拒絕，不複製也不寫資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this, FishAudio::MAX_BYTES + 1);
    $this->storage->shouldNotReceive('copyObject');

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
});

it('複製失敗時回錯誤且不寫資料', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->andReturn(false);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
});

it('寫入資料庫失敗時回滾，並刪掉已複製到正式資料夾的檔', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
    pendingExists($this);
    $this->storage->shouldReceive('copyObject')->andReturn(true);
    $this->storage->shouldReceive('delete')->once()->with('audios/'.PENDING_NAME)->andReturn(true);
    FishAudio::creating(fn () => throw new RuntimeException('寫入失敗'));

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(500);

    expect(FishAudio::count())->toBe(0);
    expect($fish->fresh()->audio_filename)->toBe('main.m4a');
});

it('名稱不在這隻魚的名稱內被拒絕', function () {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody(['spoken_name' => '別的名字']))
        ->assertStatus(422)->assertJsonValidationErrors(['spoken_name']);

    expect(FishAudio::count())->toBe(0);
});

it('魚名為空時拒絕新增', function () {
    $fish = Fish::factory()->create(['name' => '']);
    pendingExists($this);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertStatus(422);

    expect(FishAudio::count())->toBe(0);
});

it('部落必填且須是可選的部落、長度必填且不超過 5 秒、檔名格式要對', function (array $override, string $field) {
    $fish = Fish::factory()->create(['name' => 'mivazlt']);
    pendingExists($this);

    $this->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody($override))
        ->assertStatus(422)->assertJsonValidationErrors([$field]);

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

    $this->actingAs(User::factory()->lineViewer()->create())
        ->postJson("/prefix/api/fish/{$fish->id}/audio/confirm", confirmBody())->assertForbidden();
});
