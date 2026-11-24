<?php

namespace App\Console\Commands;

use App\Models\Fish;
use App\Services\FishNameSplitter;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 發音舊資料修正（#159）：locate 只留真正的部落、被改壞的 name 修回檔名、只有一個名稱的魚補名稱。
 * 執行前整張 fish_audios 備份成 fish_audios_backup_<時間>；--restore 從指定備份還原。
 *
 * 並發與還原靠 fish_audios.revision（每次 Eloquent 更新加 1，見 FishAudioBuilder）：
 * - 修正只處理備份表（同一份快照）內的舊資料，且只在該筆目前的 revision 仍等於快照的 revision 時才寫入，
 *   所以備份之後、寫入之前被人改過（任何欄位、軟刪除、改了又改回）的紀錄一律跳過並列報。
 * - 修正寫入後 revision 加 1，並把加 1 後的值記為備份表的 after_revision；
 *   還原只回復目前 revision 仍等於 after_revision 的紀錄（修正後沒有人再動過）。
 */
class RepairFishAudios extends Command
{
    protected $signature = 'fish-audios:repair
        {--dry-run : 只印各情況筆數，不備份也不改資料}
        {--restore= : 從指定備份表還原 name、locate、spoken_name（先預覽並確認）}';

    protected $description = '發音舊資料修正（先備份）；--restore 從備份還原。';

    private const BACKUP_PREFIX = 'fish_audios_backup_';

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
                    $table->unsignedInteger('after_revision')->nullable();
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

        // 舊資料＝名稱欄與錄音者欄皆為空；已有名稱或錄音者的紀錄一律不動。
        // 正式執行時從備份表（同一份快照）讀，預覽則讀現況。
        $legacy = DB::table($backup ?? 'fish_audios')->whereNull('spoken_name')->whereNull('recorded_by')->orderBy('id')->get();

        foreach ($legacy as $row) {
            $changes = $this->plannedChanges($row, $splitter, $unresolved, $stats);
            if ($changes && ! $dryRun && ! $this->applyIfUnchangedSinceBackup($row, $changes, $backup)) {
                $conflicts[] = $row->id;
            }
        }

        $this->line(sprintf(
            '%s：locate 清空 %d 筆、name 修回 %d 筆、單一名稱補名稱 %d 筆、無法確定 %d 筆',
            $dryRun ? '預覽（未修改）' : '完成',
            $stats['locate_cleared'], $stats['name_repaired'], $stats['spoken_name_filled'], count($unresolved)
        ));
        $this->reportIds('無法確定、保留原值請人工處理的紀錄', $unresolved);
        $this->reportIds('備份後被人改過而跳過（可重跑）的紀錄', $conflicts);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> 要寫入的欄位（空陣列＝不需要改） */
    private function plannedChanges(object $row, FishNameSplitter $splitter, array &$unresolved, array &$stats): array
    {
        $changes = [];
        $name = $row->name;
        $locate = $row->locate;

        if (! $this->looksLikeFilename($name)) {
            if ($this->looksLikeFilename($locate)) {
                $changes['name'] = $locate;
                $locate = null;
                $changes['locate'] = null;
                $stats['name_repaired']++;
            } else {
                $unresolved[] = $row->id;
            }
        }

        if ($locate !== null && ! in_array($locate, config('fish_options.tribes', []), true)) {
            $changes['locate'] = null;
            $stats['locate_cleared']++;
        }

        $fishNames = $splitter->split(Fish::withTrashed()->find($row->fish_id)?->name);
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
     * 只在這筆的 revision 仍等於備份快照的 revision 時寫入，並記下寫入後的 revision。
     * 用 query builder 寫入，不更新 updated_at（資料修正不算使用者編輯）。
     */
    private function applyIfUnchangedSinceBackup(object $row, array $changes, string $backup): bool
    {
        return DB::transaction(function () use ($row, $changes, $backup) {
            $updated = DB::table('fish_audios')
                ->where('id', $row->id)
                ->where('revision', $row->revision)
                ->update($changes + ['revision' => $this->incrementedRevision()]);
            if ($updated === 0) {
                return false;
            }

            DB::table($backup)->where('id', $row->id)->update(['repaired' => true, 'after_revision' => $row->revision + 1]);

            return true;
        });
    }

    private function incrementedRevision(): Expression
    {
        return DB::raw(DB::getQueryGrammar()->wrap('revision').' + 1');
    }

    private function reportIds(string $label, array $ids): void
    {
        if ($ids) {
            $this->warn("{$label}：".implode(', ', array_map(fn ($id) => "#{$id}", $ids)));
        }
    }

    private function restore(string $backup): int
    {
        if (! preg_match('/^'.self::BACKUP_PREFIX.'\d{14}$/', $backup) || ! Schema::hasTable($backup) || ! Schema::hasColumn($backup, 'after_revision')) {
            $this->error("找不到本指令建立的備份表 {$backup}（格式應為 ".self::BACKUP_PREFIX.'YYYYMMDDHHMMSS）');

            return self::FAILURE;
        }

        // 只考慮當時真的被修正過的紀錄；修正後沒有人再動過（revision 未變）才還原
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
                $count = $this->restoreQuery($old)->update([
                    'name' => $old->name,
                    'locate' => $old->locate,
                    'spoken_name' => $old->spoken_name,
                    'revision' => $this->incrementedRevision(),
                ]);
                $count === 0 ? $changedMeanwhile[] = $old->id : $restored++;
            }
        });
        $this->info("已還原 {$restored} 筆");
        $this->reportIds('預覽後被改過而跳過的紀錄', $changedMeanwhile);

        return self::SUCCESS;
    }

    private function restoreQuery(object $old): Builder
    {
        return DB::table('fish_audios')->where('id', $old->id)->where('revision', $old->after_revision);
    }
}
