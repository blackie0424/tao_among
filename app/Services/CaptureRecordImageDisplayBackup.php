<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * 舊「圖片顯示調整」（image_position、image_scale）在欄位移除前的備份與還原。
 * 備份是 S3 上的一個私有 JSON 檔：所有舊欄位有值的捕獲紀錄（含軟刪除），寫入後讀回比對。
 */
class CaptureRecordImageDisplayBackup
{
    public const PATH = 'backups/capture_records_image_display_legacy.json';

    /**
     * 寫入備份並讀回驗證；失敗一律丟例外（呼叫端不得繼續刪欄位）。沒有舊值時也寫出空備份。
     * 已有備份檔時：內容相同視為成功（重跑），不同則中止、不覆蓋。
     *
     * @return int 備份的紀錄筆數
     */
    public function write(): int
    {
        $rows = $this->currentRows();

        if ($this->disk()->exists(self::PATH)) {
            if ($this->read() !== $rows) {
                throw new RuntimeException('已有不同內容的舊顯示設定備份（'.self::PATH.'），為避免覆蓋已中止，請人工確認');
            }

            return count($rows);
        }

        $content = json_encode(['backed_up_at' => now()->toIso8601String(), 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // 備份是內部資料，一律私有寫入
        $written = $this->disk()->put(self::PATH, $content, ['visibility' => 'private', 'ContentType' => 'application/json']);
        if (! $written) {
            throw new RuntimeException('舊顯示設定備份寫入失敗：'.self::PATH);
        }

        if ($this->read() !== $rows) {
            throw new RuntimeException('舊顯示設定備份讀回內容與資料表不符：'.self::PATH);
        }

        return count($rows);
    }

    /**
     * 讀取並驗證備份；找不到、無法解析、或任一筆結構不合法時丟例外，並說明人工還原方式。
     *
     * @return array<int, array{id:int, image_position:?string, image_scale:?string}>
     */
    public function read(): array
    {
        $hint = '請確認 S3 的 '.self::PATH.'，並依其中的 rows 手動把 image_position、image_scale 寫回 capture_records';

        if (! $this->disk()->exists(self::PATH)) {
            throw new RuntimeException('找不到舊顯示設定備份。'.$hint);
        }

        $decoded = json_decode((string) $this->disk()->get(self::PATH), true);
        if (! is_array($decoded) || ! is_array($decoded['rows'] ?? null)) {
            throw new RuntimeException('舊顯示設定備份無法解析。'.$hint);
        }

        foreach ($decoded['rows'] as $index => $row) {
            if (! $this->isValidRow($row)) {
                throw new RuntimeException("舊顯示設定備份第 {$index} 筆結構不合法。".$hint);
            }
        }

        return $decoded['rows'];
    }

    /**
     * 把備份的值寫回（欄位需已存在，且 $rows 需來自 read()）。備份後才新增的紀錄沒有舊值，不受影響；已被刪除的紀錄略過。
     *
     * @param  array<int, array{id:int, image_position:?string, image_scale:?string}>  $rows
     */
    public function restore(array $rows): int
    {
        foreach ($rows as $row) {
            DB::table('capture_records')->where('id', $row['id'])->update([
                'image_position' => $row['image_position'],
                'image_scale' => $row['image_scale'],
            ]);
        }

        return count($rows);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.legacy_backup_disk'));
    }

    /** 一筆備份：整數編號、位置為字串或空、縮放為數字（字串或數值）或空 */
    private function isValidRow(mixed $row): bool
    {
        return is_array($row)
            && array_key_exists('image_position', $row) && array_key_exists('image_scale', $row)
            && is_int($row['id'] ?? null)
            && ($row['image_position'] === null || is_string($row['image_position']))
            && ($row['image_scale'] === null || is_numeric($row['image_scale']));
    }

    /** 舊欄位有值的所有紀錄（含軟刪除），依編號排序；縮放一律轉成字串，方便比對 */
    private function currentRows(): array
    {
        return DB::table('capture_records')
            ->where(fn ($query) => $query->whereNotNull('image_position')->orWhereNotNull('image_scale'))
            ->orderBy('id')
            ->get(['id', 'image_position', 'image_scale'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'image_position' => $row->image_position,
                'image_scale' => $row->image_scale === null ? null : (string) $row->image_scale,
            ])
            ->all();
    }
}
