<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishAudioRecordingService;
use App\Services\FishNameSplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    it('只差大小寫視為不同名稱', function () {
        expect((new FishNameSplitter)->split('Angsa、angsa'))->toBe(['Angsa', 'angsa']);
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

    it('沒有錄音的名稱也列出（空清單）；軟刪除的不算', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $gone = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivanivang']);
        $gone->delete();

        $grouped = $this->service->groupByName($fish->fresh());

        expect($grouped['groups']['mivanivang'])->toHaveCount(0);
        expect($grouped['unassigned'])->toHaveCount(0);
    });
});
