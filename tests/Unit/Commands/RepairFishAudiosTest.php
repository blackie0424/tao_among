<?php

use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, RefreshDatabase::class);

function legacyAudio(Fish $fish, array $attrs): FishAudio
{
    $audio = FishAudio::factory()->create(array_merge(['fish_id' => $fish->id], $attrs));
    // 舊資料的時間固定，方便驗證修正不動更新時間
    DB::table('fish_audios')->where('id', $audio->id)->update(['updated_at' => '2026-01-01 00:00:00']);

    return $audio;
}

function backupTables(): array
{
    return collect(Schema::getTables())->pluck('name')->filter(fn ($n) => str_starts_with($n, 'fish_audios_backup_'))->values()->all();
}

describe('舊資料修正', function () {
    it('--dry-run 只印筆數，不備份也不改資料', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $a = legacyAudio($fish, ['name' => 'x.m4a', 'locate' => 'supabase']);

        $this->artisan('fish-audios:repair', ['--dry-run' => true])->assertSuccessful();

        expect(backupTables())->toBe([]);
        expect($a->fresh()->locate)->toBe('supabase');
    });

    it('先備份全表再修正；不在六個部落內的 locate 一律改為空', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $a = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'supabase']);
        $b = legacyAudio($fish, ['name' => 'b.m4a', 'locate' => 'unknown']);
        $c = legacyAudio($fish, ['name' => 'c.m4a', 'locate' => '某個未知字串']);
        $d = legacyAudio($fish, ['name' => 'd.m4a', 'locate' => 'yayo']);

        $this->artisan('fish-audios:repair')->assertSuccessful();

        expect(backupTables())->toHaveCount(1);
        expect(DB::table(backupTables()[0])->count())->toBe(4);
        expect($a->fresh()->locate)->toBeNull();
        expect($b->fresh()->locate)->toBeNull();
        expect($c->fresh()->locate)->toBeNull();
        expect($d->fresh()->locate)->toBe('yayo');
    });

    it('被改壞的 name 以 locate 內的檔名修回，locate 清空', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $broken = legacyAudio($fish, ['name' => '太魯閣族發音', 'locate' => 'uuid-1234.m4a']);

        $this->artisan('fish-audios:repair')->assertSuccessful();

        expect($broken->fresh()->only(['name', 'locate']))->toBe(['name' => 'uuid-1234.m4a', 'locate' => null]);
    });

    it('合法但格式不同的檔名不動（大小寫副檔名、webm）', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $ok = legacyAudio($fish, ['name' => 'rec_1.WEBM', 'locate' => 'yayo']);

        $this->artisan('fish-audios:repair')->assertSuccessful();

        expect($ok->fresh()->name)->toBe('rec_1.WEBM');
    });

    it('無法確定的紀錄保留原值並列出編號', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $unsure = legacyAudio($fish, ['name' => '亂打的名字', 'locate' => 'yayo']);

        $this->artisan('fish-audios:repair')
            ->expectsOutputToContain("#{$unsure->id}")
            ->assertSuccessful();

        expect($unsure->fresh()->only(['name', 'locate']))->toBe(['name' => '亂打的名字', 'locate' => 'yayo']);
    });

    it('單一名稱的魚自動填名稱；多名稱的魚留空', function () {
        $single = Fish::factory()->create(['name' => 'Angsa']);
        $multi = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $s = legacyAudio($single, ['name' => 's.m4a', 'locate' => 'yayo']);
        $m = legacyAudio($multi, ['name' => 'm.m4a', 'locate' => 'yayo']);

        $this->artisan('fish-audios:repair')->assertSuccessful();

        expect($s->fresh()->spoken_name)->toBe('Angsa');
        expect($m->fresh()->spoken_name)->toBeNull();
    });

    it('重跑不會動已有名稱（手動指派或新紀錄）或有錄音者的紀錄', function () {
        $multi = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $assigned = legacyAudio($multi, ['name' => 'a.m4a', 'locate' => 'yayo', 'spoken_name' => 'mivanivang']);
        $recorded = legacyAudio($multi, ['name' => 'b.m4a', 'locate' => 'unknown', 'recorded_by' => 5]);
        $legacy = legacyAudio($multi, ['name' => 'c.m4a', 'locate' => 'supabase']);

        $this->artisan('fish-audios:repair')->assertSuccessful();
        // 田調人員之後把舊的那筆指派到某個名稱
        $legacy->update(['spoken_name' => 'mivazlt']);
        $this->travel(2)->seconds(); // 備份表名以秒計，重跑要晚一秒以上
        $this->artisan('fish-audios:repair')->assertSuccessful();

        expect($assigned->fresh()->only(['spoken_name', 'locate']))->toBe(['spoken_name' => 'mivanivang', 'locate' => 'yayo']);
        expect($recorded->fresh()->locate)->toBe('unknown');
        expect($legacy->fresh()->spoken_name)->toBe('mivazlt');
    });

    it('備份失敗就不改任何資料', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $a = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'supabase']);
        // 讓備份表名稱已存在而建立失敗
        $name = 'fish_audios_backup_'.now()->format('YmdHis');
        DB::statement("CREATE TABLE {$name} (id integer)");

        $this->artisan('fish-audios:repair')->assertFailed();

        expect($a->fresh()->locate)->toBe('supabase');
    });
});

describe('還原', function () {
    it('指定備份表、預覽並確認後，只還原備份後沒被改過的紀錄的三個欄位', function () {
        $fish = Fish::factory()->create(['name' => 'Angsa']);
        $untouched = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'supabase']);
        $edited = legacyAudio($fish, ['name' => 'b.m4a', 'locate' => 'unknown']);

        $this->artisan('fish-audios:repair')->assertSuccessful();
        $backup = backupTables()[0];

        // 修正後田調人員又編輯了其中一筆；另有備份後新增的紀錄
        $edited->update(['locate' => 'iratay']);
        $added = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'new.m4a', 'locate' => 'yayo', 'spoken_name' => 'Angsa']);

        $this->artisan('fish-audios:repair', ['--restore' => $backup])
            ->expectsConfirmation('確定要還原嗎？', 'yes')
            ->assertSuccessful();

        expect($untouched->fresh()->only(['locate', 'spoken_name']))->toBe(['locate' => 'supabase', 'spoken_name' => null]);
        expect($edited->fresh()->locate)->toBe('iratay');
        expect($added->fresh()->only(['locate', 'spoken_name']))->toBe(['locate' => 'yayo', 'spoken_name' => 'Angsa']);
    });

    it('預覽後回答否就不還原', function () {
        $fish = Fish::factory()->create(['name' => 'Angsa']);
        $a = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'supabase']);
        $this->artisan('fish-audios:repair')->assertSuccessful();
        $backup = backupTables()[0];

        $this->artisan('fish-audios:repair', ['--restore' => $backup])
            ->expectsConfirmation('確定要還原嗎？', 'no')
            ->assertSuccessful();

        expect($a->fresh()->locate)->toBeNull();
    });

    it('指定不存在或名稱不合規的備份表時失敗且不改資料', function () {
        $fish = Fish::factory()->create(['name' => 'Angsa']);
        $a = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'yayo']);

        $this->artisan('fish-audios:repair', ['--restore' => 'fish_audios_backup_20200101000000'])->assertFailed();
        $this->artisan('fish-audios:repair', ['--restore' => 'users'])->assertFailed();

        expect($a->fresh()->locate)->toBe('yayo');
    });

    it('有多份備份時只用指定的那份', function () {
        $fish = Fish::factory()->create(['name' => 'Angsa']);
        $a = legacyAudio($fish, ['name' => 'a.m4a', 'locate' => 'supabase']);
        $this->artisan('fish-audios:repair')->assertSuccessful();
        $first = backupTables()[0];
        // 第二份備份（修正後的狀態），名稱晚一秒
        $second = 'fish_audios_backup_'.now()->addSecond()->format('YmdHis');
        DB::statement("CREATE TABLE {$second} AS SELECT * FROM fish_audios");

        $this->artisan('fish-audios:repair', ['--restore' => $first])
            ->expectsConfirmation('確定要還原嗎？', 'yes')
            ->assertSuccessful();

        expect($a->fresh()->locate)->toBe('supabase');
    });
});
