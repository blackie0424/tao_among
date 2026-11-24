<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishAudioRecordingService;
use App\Services\FishNameSplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery as m;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->storage = m::mock(StorageServiceInterface::class);
    $this->storage->shouldReceive('getAudioFolder')->andReturn('audios')->byDefault();
    $this->storage->shouldReceive('delete')->andReturn(true)->byDefault();
    $this->storage->shouldReceive('getUrl')->andReturn('https://example.test/a.m4a')->byDefault();
    app()->instance(StorageServiceInterface::class, $this->storage);
    $this->service = new FishAudioRecordingService($this->storage, new FishNameSplitter);
});

describe('FishNameSplitter', function () {
    it('依「、」拆開、去頭尾空白、略過空字串、重複只留一個並保留順序', function () {
        $splitter = new FishNameSplitter;

        expect($splitter->split(' mivazlt、、 mivanivang 、mivazlt、'))->toBe(['mivazlt', 'mivanivang']);
        expect($splitter->split('Angsa'))->toBe(['Angsa']);
        expect($splitter->split(''))->toBe([]);
        expect($splitter->split(null))->toBe([]);
    });

    it('去頭尾空白的字元與前端 trim() 一致：全形空白、不換行空白、BOM 都要去掉（前端 fishSpellings.spec.js 用同一組案例）', function () {
        $splitter = new FishNameSplitter;

        expect($splitter->split("\u{3000}Ivey\u{3000}、Ivay"))->toBe(['Ivey', 'Ivay']);
        expect($splitter->split("\u{00A0}Ivey\u{00A0}、\u{FEFF}Ivay\u{FEFF}"))->toBe(['Ivey', 'Ivay']);
        expect($splitter->split("\u{2003}Ivey\u{2009}、Ivay\n"))->toBe(['Ivey', 'Ivay']);
        expect($splitter->split("Iv\u{3000}ey"))->toBe(["Iv\u{3000}ey"]); // 中間的空白不動
        // JavaScript trim() 不會去掉、PHP 的 \s 會去掉的字元：兩邊都必須保留
        expect($splitter->split("\u{0085}Ivey\u{0085}、Ivay"))->toBe(["\u{0085}Ivey\u{0085}", 'Ivay']);
        expect($splitter->split("\u{180E}Ivey、Ivay"))->toBe(["\u{180E}Ivey", 'Ivay']);
        expect($splitter->split("\u{3000}、\u{3000}"))->toBe([]);
    });

    it('只差大小寫視為不同名稱', function () {
        expect((new FishNameSplitter)->split('Angsa、angsa'))->toBe(['Angsa', 'angsa']);
    });
});

describe('魚名含全形空白：建立的名稱與魚頁（前端拆名）對得起來', function () {
    it('以全形空白包住的拼字建立錄音，記下的名稱是去掉空白後的拼字，兩個拼字各自分組', function () {
        $fish = Fish::factory()->create(['name' => "\u{3000}Ivey\u{3000}、Ivay"]);

        $a = $this->service->create($fish, 'Ivey', 'yayo', 'a.m4a', 3000, null);
        $b = $this->service->create($fish, 'Ivay', 'yayo', 'b.m4a', 3000, null);

        expect($a->fresh()->spoken_name)->toBe('Ivey');
        expect(array_keys($this->service->groupByName($fish->fresh())['groups']))->toBe(['Ivey', 'Ivay']);
        expect($this->service->groupByName($fish->fresh())['unassigned'])->toHaveCount(0);
        expect($b->fresh()->spoken_name)->toBe('Ivay');
    });

    it('帶著全形空白的名稱不被接受（名稱必須是拆分後的拼字，完全相同才算）', function () {
        $fish = Fish::factory()->create(['name' => "\u{3000}Ivey\u{3000}、Ivay"]);

        expect(fn () => $this->service->create($fish, "\u{3000}Ivey\u{3000}", 'yayo', 'a.m4a', 3000, null))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('create()', function () {
    it('同名稱同部落再新增是新增一筆，不覆蓋、不刪舊檔', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $this->storage->shouldNotReceive('delete');

        $first = $this->service->create($fish, 'mivazlt', 'iraraley', 'a1.m4a', 4000, 7);
        $second = $this->service->create($fish, 'mivazlt', 'iraraley', 'a2.m4a', 5000, 8);

        expect(FishAudio::where('fish_id', $fish->id)->count())->toBe(2);
        expect($first->fresh()->only(['name', 'locate', 'spoken_name', 'duration', 'recorded_by']))
            ->toBe(['name' => 'a1.m4a', 'locate' => 'iraraley', 'spoken_name' => 'mivazlt', 'duration' => 4000, 'recorded_by' => 7]);
        expect($second->fresh()->name)->toBe('a2.m4a');
    });

    it('名稱不在該魚的名稱內時被拒絕且不寫資料', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);

        expect(fn () => $this->service->create($fish, '別的名字', 'iraraley', 'a.m4a', 3000, null))
            ->toThrow(InvalidArgumentException::class);
        expect(FishAudio::count())->toBe(0);
    });

    it('部落不在六個部落內時被拒絕', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);

        expect(fn () => $this->service->create($fish, 'mivazlt', 'supabase', 'a.m4a', 3000, null))
            ->toThrow(InvalidArgumentException::class);
        expect(FishAudio::count())->toBe(0);
    });

    it('魚名為空時被拒絕', function () {
        $fish = Fish::factory()->create(['name' => '']);

        expect(fn () => $this->service->create($fish, 'x', 'iraraley', 'a.m4a', 3000, null))
            ->toThrow(InvalidArgumentException::class);
    });

    it('新增時照現況設定主要發音為新檔', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'old.m4a']);

        $this->service->create($fish, 'mivazlt', 'yayo', 'new.m4a', 3000, null);

        expect($fish->fresh()->audio_filename)->toBe('new.m4a');
    });
});

describe('create() 過渡情境（LINE 選名稱流程上線前）', function () {
    it('名稱為 null：單一名稱的魚自動帶入，多名稱的魚留空（歸未對應名稱）', function () {
        $single = Fish::factory()->create(['name' => 'Angsa']);
        $multi = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);

        $s = $this->service->create($single, null, 'yayo', 's.m4a', 3000, null);
        $m = $this->service->create($multi, null, 'yayo', 'm.m4a', 3000, null);

        expect($s->fresh()->spoken_name)->toBe('Angsa');
        expect($m->fresh()->spoken_name)->toBeNull();
        expect($this->service->groupByName($multi->fresh())['unassigned']->pluck('id')->all())->toBe([$m->id]);
    });

    it('名稱為 null 但魚名為空時仍被拒絕', function () {
        $fish = Fish::factory()->create(['name' => '']);

        expect(fn () => $this->service->create($fish, null, 'yayo', 'a.m4a', 3000, null))->toThrow(InvalidArgumentException::class);
    });
});

describe('replaceFile()', function () {
    it('替換的是主要發音檔：指標同交易改為新檔名，舊檔被刪', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'old.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo', 'spoken_name' => 'mivazlt']);
        $this->storage->shouldReceive('delete')->once()->with('audios/old.m4a')->andReturn(true);

        $this->service->replaceFile($audio, 'new.m4a', 4500);

        expect($audio->fresh()->only(['name', 'locate', 'duration']))->toBe(['name' => 'new.m4a', 'locate' => 'yayo', 'duration' => 4500]);
        expect($fish->fresh()->audio_filename)->toBe('new.m4a');
    });

    it('替換的不是主要發音檔：主要發音指標不動', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'other.m4a', 'spoken_name' => 'mivazlt']);

        $this->service->replaceFile($audio, 'new.m4a', 4000);

        expect($fish->fresh()->audio_filename)->toBe('main.m4a');
    });
});

describe('replaceFile() 與外層交易', function () {
    it('外層交易回滾時舊檔不刪，資料仍指向舊檔', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'old.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'spoken_name' => 'mivazlt']);
        $this->storage->shouldNotReceive('delete');

        try {
            DB::transaction(function () use ($audio) {
                $this->service->replaceFile($audio, 'new.m4a', 4000);
                throw new RuntimeException('外層後續步驟失敗');
            });
        } catch (RuntimeException) {
        }

        expect($audio->fresh()->name)->toBe('old.m4a');
        expect($fish->fresh()->audio_filename)->toBe('old.m4a');
    });

    it('外層交易提交後才刪舊檔', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'old.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'spoken_name' => 'mivazlt']);
        $this->storage->shouldReceive('delete')->once()->with('audios/old.m4a')->andReturn(true);

        DB::transaction(fn () => $this->service->replaceFile($audio, 'new.m4a', 4000));
    });
});

describe('updateTribe()', function () {
    it('只改部落，name 不變', function () {
        $audio = FishAudio::factory()->create(['name' => 'keep.m4a', 'locate' => 'yayo']);

        $this->service->updateTribe($audio, 'iratay');

        expect($audio->fresh()->only(['name', 'locate']))->toBe(['name' => 'keep.m4a', 'locate' => 'iratay']);
    });

    it('不在六個部落內時被拒絕', function () {
        $audio = FishAudio::factory()->create(['locate' => 'yayo']);

        expect(fn () => $this->service->updateTribe($audio, 'unknown'))->toThrow(InvalidArgumentException::class);
        expect($audio->fresh()->locate)->toBe('yayo');
    });
});

describe('delete()', function () {
    it('刪除的是主要發音那段：指標改指剩餘最新的一段', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'b.m4a']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'a.m4a', 'spoken_name' => 'mivazlt']);
        $b = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'b.m4a', 'spoken_name' => 'mivazlt']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'c.m4a', 'spoken_name' => 'mivazlt']);

        $this->service->delete($b);

        expect($fish->fresh()->audio_filename)->toBe('c.m4a');
        expect(FishAudio::where('fish_id', $fish->id)->count())->toBe(2);
    });

    it('刪除的是主要發音那段且沒有剩餘：指標清空', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'a.m4a']);
        $a = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'a.m4a', 'spoken_name' => 'mivazlt']);

        $this->service->delete($a);

        expect($fish->fresh()->audio_filename)->toBeNull();
    });

    it('刪除的不是主要發音那段：指標不動', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'main.m4a']);
        $other = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'other.m4a', 'spoken_name' => 'mivazlt']);

        $this->service->delete($other);

        expect($fish->fresh()->audio_filename)->toBe('main.m4a');
    });
});

describe('assignName()', function () {
    it('指派到該魚存在的名稱', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        $this->service->assignName($audio, 'mivanivang');

        expect($audio->fresh()->spoken_name)->toBe('mivanivang');
    });

    it('指派不存在的名稱被拒絕', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        expect(fn () => $this->service->assignName($audio, '不存在'))->toThrow(InvalidArgumentException::class);
        expect($audio->fresh()->spoken_name)->toBeNull();
    });
});

describe('setPrimary()：每個拼字各自的主要發音', function () {
    it('指定後同拼字其他段取消、其他拼字不受影響，fish.audio_filename 一併指向它', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay', 'audio_filename' => null]);
        $e1 = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'e1.m4a', 'spoken_name' => 'Ivey', 'is_primary' => true]);
        $e2 = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'e2.m4a', 'spoken_name' => 'Ivey']);
        $a1 = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'a1.m4a', 'spoken_name' => 'Ivay', 'is_primary' => true]);

        $this->service->setPrimary($e2);

        expect($e1->fresh()->is_primary)->toBeFalse();
        expect($e2->fresh()->is_primary)->toBeTrue();
        expect($a1->fresh()->is_primary)->toBeTrue(); // 另一個拼字的主要發音不被蓋掉
        expect($fish->fresh()->audio_filename)->toBe('e2.m4a');
    });

    it('沒有拼字的錄音（未對應名稱）不能指定，要先指派名稱', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay', 'audio_filename' => 'keep.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        expect(fn () => $this->service->setPrimary($audio))->toThrow(InvalidArgumentException::class);
        expect($audio->fresh()->is_primary)->toBeFalse();
        expect($fish->fresh()->audio_filename)->toBe('keep.m4a');
    });

    it('拼字已不在魚名裡（魚改名後）的錄音不能指定', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivay']);

        expect(fn () => $this->service->setPrimary($audio))->toThrow(InvalidArgumentException::class);
    });

    it('改派到別的拼字時，原本的主要發音標記一併清除；改派到同一個拼字則保留', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey', 'is_primary' => true]);

        $this->service->assignName($audio, 'Ivey');
        expect($audio->fresh()->is_primary)->toBeTrue();

        $this->service->assignName($audio->fresh(), 'Ivay');
        expect($audio->fresh()->only(['spoken_name', 'is_primary']))->toBe(['spoken_name' => 'Ivay', 'is_primary' => false]);
    });

    it('只差大小寫的兩個拼字各自指定：指定一個不會取消另一個（資料庫字串比對可能不分大小寫，要完全相同才算同拼字）', function () {
        $fish = Fish::factory()->create(['name' => 'Angsa、angsa']);
        $upper = [FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'u1.m4a', 'spoken_name' => 'Angsa']), FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'u2.m4a', 'spoken_name' => 'Angsa'])];
        $lower = [FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'l1.m4a', 'spoken_name' => 'angsa']), FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'l2.m4a', 'spoken_name' => 'angsa'])];

        $this->service->setPrimary($upper[1]); // 各拼字分別指定「不是最早」的一段
        $this->service->setPrimary($lower[1]);

        expect(array_map(fn ($a) => $a->fresh()->is_primary, [...$upper, ...$lower]))->toBe([false, true, false, true]);
    });

    it('並發：指定的請求拿著過期的錄音（已被改派到別的拼字）時，以最新的拼字為準，不會讓新拼字出現兩個主要發音', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey']);
        $existing = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivay', 'is_primary' => true]);
        $stale = FishAudio::find($audio->id);
        FishAudio::find($audio->id)->update(['spoken_name' => 'Ivay']); // 另一個請求先把它改派到 Ivay

        $this->service->setPrimary($stale);

        $ivay = FishAudio::where('fish_id', $fish->id)->where('spoken_name', 'Ivay')->get();
        expect($ivay->where('is_primary', true)->pluck('id')->all())->toBe([$audio->id]);
        expect($existing->fresh()->is_primary)->toBeFalse();
    });

    it('並發：改派的請求拿著過期的錄音（其實已是主要發音）時，改派後主要發音標記仍會清除', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey']);
        $stale = FishAudio::find($audio->id); // 這時還不是主要發音
        $this->service->setPrimary(FishAudio::find($audio->id)); // 另一個請求先把它指定為主要發音

        $this->service->assignName($stale, 'Ivay');

        expect($audio->fresh()->only(['spoken_name', 'is_primary']))->toBe(['spoken_name' => 'Ivay', 'is_primary' => false]);
    });

    it('並發：錄音已被刪除或拼字已因魚改名消失，指定被拒絕且魚的目前發音檔不變', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay', 'audio_filename' => 'keep.m4a']);
        $deleted = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey']);
        $renamed = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivay']);
        $staleDeleted = FishAudio::find($deleted->id);
        $staleRenamed = FishAudio::find($renamed->id);
        FishAudio::find($deleted->id)->delete();
        $fish->update(['name' => 'Ivey']); // Ivay 已不在魚名裡

        expect(fn () => $this->service->setPrimary($staleDeleted))->toThrow(InvalidArgumentException::class);
        expect(fn () => $this->service->setPrimary($staleRenamed))->toThrow(InvalidArgumentException::class);
        expect($fish->fresh()->audio_filename)->toBe('keep.m4a');
        expect($renamed->fresh()->is_primary)->toBeFalse();
    });

    it('新增、刪除錄音不會留下兩個主要發音：刪掉主要發音那段後該拼字回到最早一段（沒有標記）', function () {
        $fish = Fish::factory()->create(['name' => 'Ivey']);
        $first = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey']);
        $primary = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey', 'is_primary' => true]);

        $this->service->delete($primary);

        expect(FishAudio::where('fish_id', $fish->id)->where('is_primary', true)->count())->toBe(0);
        expect($first->fresh()->is_primary)->toBeFalse();
    });
});

describe('groupByName()', function () {
    it('名稱空或不在目前名稱內的歸未對應；魚改名後改回同字串自動歸回', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $a = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivazlt']);
        $b = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivanivang']);
        $c = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        $grouped = $this->service->groupByName($fish->fresh());
        expect(array_keys($grouped['groups']))->toBe(['mivazlt', 'mivanivang']);
        expect($grouped['groups']['mivazlt']->pluck('id')->all())->toBe([$a->id]);
        expect($grouped['unassigned']->pluck('id')->all())->toBe([$c->id]);

        $fish->update(['name' => 'mivazlt']);
        $renamed = $this->service->groupByName($fish->fresh());
        expect($renamed['unassigned']->pluck('id')->sort()->values()->all())->toBe([$b->id, $c->id]);

        $fish->update(['name' => 'mivazlt、mivanivang']);
        $back = $this->service->groupByName($fish->fresh());
        expect($back['groups']['mivanivang']->pluck('id')->all())->toBe([$b->id]);
    });

    it('名稱比對為完全相同的字串：1 與 01 是不同名稱，錄音只在自己那組', function () {
        $fish = Fish::factory()->create(['name' => '1、01']);
        $one = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => '1']);
        $zeroOne = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => '01']);

        $grouped = $this->service->groupByName($fish->fresh());

        expect($grouped['groups']['1']->pluck('id')->all())->toBe([$one->id]);
        expect($grouped['groups']['01']->pluck('id')->all())->toBe([$zeroOne->id]);
    });

    it('沒有錄音的名稱也列出（空清單）；軟刪除的不算', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $gone = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivanivang']);
        $gone->delete();

        $grouped = $this->service->groupByName($fish->fresh());

        expect($grouped['groups']['mivanivang'])->toHaveCount(0);
        expect($grouped['unassigned'])->toHaveCount(0);
    });
});
