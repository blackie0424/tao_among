<?php

namespace App\Console\Commands;

use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishNameSplitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 發音舊資料修正（#159）：locate 只留真正的部落、被改壞的 name 修回檔名、只有一個名稱的魚補名稱。
 * 執行前整張 fish_audios 備份成 fish_audios_backup_<時間>；--restore 從指定備份還原。
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

        if (! $dryRun) {
            $backup = self::BACKUP_PREFIX.now()->format('YmdHis');
            try {
                DB::statement("CREATE TABLE {$backup} AS SELECT * FROM fish_audios");
            } catch (Throwable $e) {
                $this->error('備份失敗，未修改任何資料：'.$e->getMessage());

                return self::FAILURE;
            }
            $this->info("已備份到 {$backup}");
        }

        $stats = ['locate_cleared' => 0, 'name_repaired' => 0, 'spoken_name_filled' => 0];
        $unresolved = [];

        // 舊資料＝名稱欄與錄音者欄皆為空；已有名稱或錄音者的紀錄一律不動
        $legacy = FishAudio::withTrashed()->whereNull('spoken_name')->whereNull('recorded_by')->orderBy('id')->get();

        foreach ($legacy as $audio) {
            $changes = $this->plannedChanges($audio, $splitter, $unresolved, $stats);
            if ($changes && ! $dryRun) {
                $this->applyWithoutTouchingTimestamps($audio, $changes);
            }
        }

        $this->line(sprintf(
            '%s：locate 清空 %d 筆、name 修回 %d 筆、單一名稱補名稱 %d 筆、無法確定 %d 筆',
            $dryRun ? '預覽（未修改）' : '完成',
            $stats['locate_cleared'], $stats['name_repaired'], $stats['spoken_name_filled'], count($unresolved)
        ));
        if ($unresolved) {
            $this->warn('無法確定、保留原值請人工處理的紀錄：'.implode(', ', array_map(fn ($id) => "#{$id}", $unresolved)));
        }

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

    /** 不動更新時間：還原時用「更新時間沒變」判斷備份後有沒有人再編輯過。 */
    private function applyWithoutTouchingTimestamps(FishAudio $audio, array $changes): void
    {
        DB::transaction(function () use ($audio, $changes) {
            $audio->timestamps = false;
            $audio->forceFill($changes)->save();
        });
    }

    private function restore(string $backup): int
    {
        if (! preg_match('/^'.self::BACKUP_PREFIX.'\d{14}$/', $backup) || ! Schema::hasTable($backup)) {
            $this->error("找不到備份表 {$backup}（格式應為 ".self::BACKUP_PREFIX.'YYYYMMDDHHMMSS）');

            return self::FAILURE;
        }

        $restorable = [];
        $skipped = [];
        foreach (DB::table($backup)->orderBy('id')->get() as $old) {
            $current = FishAudio::withTrashed()->find($old->id);
            if (! $current || (string) $current->updated_at !== (string) $old->updated_at) {
                $skipped[] = $old->id; // 已不存在或備份後被改過，不覆蓋
            } elseif ($current->name !== $old->name || $current->locate !== $old->locate || $current->spoken_name !== $old->spoken_name) {
                $restorable[] = [$current, $old];
            }
        }

        $this->line(sprintf('預覽：會還原 %d 筆；備份後被改過或已不存在而跳過 %d 筆', count($restorable), count($skipped)));
        if ($skipped) {
            $this->warn('跳過的紀錄：'.implode(', ', array_map(fn ($id) => "#{$id}", $skipped)));
        }
        if (! $restorable || ! $this->confirm('確定要還原嗎？')) {
            $this->info('未還原任何資料');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($restorable) {
            foreach ($restorable as [$current, $old]) {
                $current->timestamps = false;
                $current->forceFill(['name' => $old->name, 'locate' => $old->locate, 'spoken_name' => $old->spoken_name])->save();
            }
        });
        $this->info(sprintf('已還原 %d 筆', count($restorable)));

        return self::SUCCESS;
    }
}
