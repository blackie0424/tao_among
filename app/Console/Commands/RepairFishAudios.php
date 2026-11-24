<?php

namespace App\Console\Commands;

use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishNameSplitter;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 發音舊資料修正（#159）：locate 只留真正的部落、被改壞的 name 修回檔名、只有一個名稱的魚補名稱。
 * 執行前整張 fish_audios 備份成 fish_audios_backup_<時間>；--restore 從指定備份還原。
 *
 * 並發與還原：每筆寫入都以「目前的欄位值仍等於讀取時／修正後的值」為條件（compare-and-swap），
 * 條件不符（讀取後有人改過）就跳過並列報，不依賴時間戳。備份表另存修正後的值（after_*），
 * 還原只回復「目前值仍等於修正後的值」的紀錄。
 */
class RepairFishAudios extends Command
{
    protected $signature = 'fish-audios:repair
        {--dry-run : 只印各情況筆數，不備份也不改資料}
        {--restore= : 從指定備份表還原 name、locate、spoken_name（先預覽並確認）}';

    protected $description = '發音舊資料修正（先備份）；--restore 從備份還原。';

    private const BACKUP_PREFIX = 'fish_audios_backup_';

    /** 受修正與還原影響的欄位 */
    private const FIELDS = ['name', 'locate', 'spoken_name'];

    /** 「檔名格式」：不含空白，副檔名為下列之一 */
    private const FILENAME_PATTERN = '/^\S+\.(m4a|aac|mp3|webm|wav)$/i';

    public function handle(FishNameSplitter $splitter): int
    {
        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }

        $dryRun = (bool) $this->option('dry-run');
        $backup = null;

        if (! $dryRun) {
            $backup = self::BACKUP_PREFIX.now()->format('YmdHis');
            try {
                DB::statement("CREATE TABLE {$backup} AS SELECT * FROM fish_audios");
                Schema::table($backup, function ($table) {
                    $table->boolean('repaired')->default(false);
                    $table->string('after_name')->nullable();
                    $table->string('after_locate')->nullable();
                    $table->string('after_spoken_name')->nullable();
                });
            } catch (Throwable $e) {
                $this->error('備份失敗，未修改任何資料：'.$e->getMessage());

                return self::FAILURE;
            }
            $this->info("已備份到 {$backup}");
        }

        $stats = ['locate_cleared' => 0, 'name_repaired' => 0, 'spoken_name_filled' => 0];
        $unresolved = [];
        $conflicts = [];

        // 舊資料＝名稱欄與錄音者欄皆為空；已有名稱或錄音者的紀錄一律不動
        $legacy = FishAudio::withTrashed()->whereNull('spoken_name')->whereNull('recorded_by')->orderBy('id')->get();

        foreach ($legacy as $audio) {
            $changes = $this->plannedChanges($audio, $splitter, $unresolved, $stats);
            if ($changes && ! $dryRun && ! $this->applyIfUnchanged($audio, $changes, $backup)) {
                $conflicts[] = $audio->id;
            }
        }

        $this->line(sprintf(
            '%s：locate 清空 %d 筆、name 修回 %d 筆、單一名稱補名稱 %d 筆、無法確定 %d 筆',
            $dryRun ? '預覽（未修改）' : '完成',
            $stats['locate_cleared'], $stats['name_repaired'], $stats['spoken_name_filled'], count($unresolved)
        ));
        $this->reportIds('無法確定、保留原值請人工處理的紀錄', $unresolved);
        $this->reportIds('讀取後被人改過而跳過（可重跑）的紀錄', $conflicts);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> 要寫入的欄位（空陣列＝不需要改） */
    private function plannedChanges(FishAudio $audio, FishNameSplitter $splitter, array &$unresolved, array &$stats): array
    {
        $changes = [];
        $name = $audio->name;
        $locate = $audio->locate;

        if (! $this->looksLikeFilename($name)) {
            if ($this->looksLikeFilename($locate)) {
                $name = $changes['name'] = $locate;
                $locate = null;
                $changes['locate'] = null;
                $stats['name_repaired']++;
            } else {
                $unresolved[] = $audio->id;
            }
        }

        if ($locate !== null && ! in_array($locate, config('fish_options.tribes', []), true)) {
            $changes['locate'] = null;
            $stats['locate_cleared']++;
        }

        $fishNames = $splitter->split(Fish::withTrashed()->find($audio->fish_id)?->name);
        if (count($fishNames) === 1) {
            $changes['spoken_name'] = $fishNames[0];
            $stats['spoken_name_filled']++;
        }

        return $changes;
    }

    private function looksLikeFilename(?string $value): bool
    {
        return $value !== null && preg_match(self::FILENAME_PATTERN, $value) === 1;
    }

    /**
     * 只在這筆的目前值仍等於讀取時的值才寫入（讀取後被人改過就不動），並把修正後的值記進備份表。
     * 用 query builder 寫入，不更新 updated_at（資料修正不算使用者編輯）。
     */
    private function applyIfUnchanged(FishAudio $audio, array $changes, ?string $backup): bool
    {
        return DB::transaction(function () use ($audio, $changes, $backup) {
            $query = DB::table('fish_audios')->where('id', $audio->id);
            foreach ([...self::FIELDS, 'recorded_by'] as $field) {
                $this->whereSame($query, $field, $audio->getRawOriginal($field));
            }
            if ($query->update($changes) === 0) {
                return false;
            }

            $after = array_merge($audio->only(self::FIELDS), $changes);
            DB::table($backup)->where('id', $audio->id)->update([
                'repaired' => true,
                'after_name' => $after['name'],
                'after_locate' => $after['locate'],
                'after_spoken_name' => $after['spoken_name'],
            ]);

            return true;
        });
    }

    private function whereSame(Builder $query, string $column, mixed $value): void
    {
        $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }

    private function reportIds(string $label, array $ids): void
    {
        if ($ids) {
            $this->warn("{$label}：".implode(', ', array_map(fn ($id) => "#{$id}", $ids)));
        }
    }

    private function restore(string $backup): int
    {
        if (! preg_match('/^'.self::BACKUP_PREFIX.'\d{14}$/', $backup) || ! Schema::hasTable($backup) || ! Schema::hasColumn($backup, 'repaired')) {
            $this->error("找不到本指令建立的備份表 {$backup}（格式應為 ".self::BACKUP_PREFIX.'YYYYMMDDHHMMSS）');

            return self::FAILURE;
        }

        // 只考慮當時真的被修正過的紀錄；目前值仍等於修正後的值才還原
        $repaired = DB::table($backup)->where('repaired', true)->orderBy('id')->get();
        $restorable = $repaired->filter(fn ($old) => $this->restoreQuery($old)->exists())->values();
        $skipped = $repaired->pluck('id')->diff($restorable->pluck('id'))->values()->all();

        $this->line(sprintf('預覽：會還原 %d 筆；修正後被改過或已不存在而跳過 %d 筆', $restorable->count(), count($skipped)));
        $this->reportIds('跳過的紀錄', $skipped);
        if ($restorable->isEmpty() || ! $this->confirm('確定要還原嗎？')) {
            $this->info('未還原任何資料');

            return self::SUCCESS;
        }

        $restored = 0;
        $changedMeanwhile = [];
        DB::transaction(function () use ($restorable, &$restored, &$changedMeanwhile) {
            foreach ($restorable as $old) {
                // 確認之後才寫入的這一刻再比對一次，預覽後被改過的不覆蓋
                $count = $this->restoreQuery($old)->update(['name' => $old->name, 'locate' => $old->locate, 'spoken_name' => $old->spoken_name]);
                $count === 0 ? $changedMeanwhile[] = $old->id : $restored++;
            }
        });
        $this->info("已還原 {$restored} 筆");
        $this->reportIds('預覽後被改過而跳過的紀錄', $changedMeanwhile);

        return self::SUCCESS;
    }

    private function restoreQuery(object $old): Builder
    {
        $query = DB::table('fish_audios')->where('id', $old->id);
        foreach (self::FIELDS as $field) {
            $this->whereSame($query, $field, $old->{'after_'.$field});
        }

        return $query;
    }
}
