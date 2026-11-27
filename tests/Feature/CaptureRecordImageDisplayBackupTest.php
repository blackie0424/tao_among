<?php

use App\Contracts\StorageServiceInterface;
use App\Models\CaptureRecord;
use App\Services\CaptureRecordImageDisplayBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const BACKUP_PATH = 'backups/capture_records_image_display_legacy.json';

/** 用記憶體當 S3：回傳 [$files（參考）, 注入的 mock] */
function fakeBackupStorage(array $overrides = []): ArrayObject
{
    $files = new ArrayObject;
    $storage = Mockery::mock(StorageServiceInterface::class);
    // 軟刪除紀錄時模型會清圖片檔，這裡不關心
    $storage->shouldReceive('getImageFolder')->andReturn('images');
    $storage->shouldReceive('delete')->andReturn(true);
    $storage->shouldReceive('fileExists')->andReturnUsing(fn ($path) => isset($files[$path]));
    $storage->shouldReceive('getContent')->andReturnUsing($overrides['getContent'] ?? fn ($path) => $files[$path]);
    $storage->shouldReceive('putContent')->andReturnUsing($overrides['putContent'] ?? function ($path, $content) use ($files) {
        $files[$path] = $content;

        return true;
    });
    app()->instance(StorageServiceInterface::class, $storage);

    return $files;
}

function dropMigration(): Migration
{
    return require base_path('database/migrations/2026_11_27_000002_drop_image_position_scale_from_capture_records.php');
}

/** 測試資料庫是跑完所有 migration 的結果（舊欄位已移除），這裡先補回舊欄位模擬「移除前」 */
function restoreLegacyColumns(): void
{
    Schema::table('capture_records', function (Blueprint $table) {
        $table->string('image_position')->nullable();
        $table->decimal('image_scale', 3, 2)->nullable();
    });
}

function legacyRecord(array $legacy, bool $trashed = false): int
{
    $record = CaptureRecord::factory()->create();
    DB::table('capture_records')->where('id', $record->id)->update($legacy);
    if ($trashed) {
        $record->delete();
    }

    return $record->id;
}

function legacyColumnsExist(): bool
{
    return Schema::hasColumns('capture_records', ['image_position', 'image_scale']);
}

beforeEach(fn () => restoreLegacyColumns());

it('備份有值的紀錄（含軟刪除），沒有值的不列，然後移除舊欄位', function () {
    $files = fakeBackupStorage();
    $a = legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);
    $b = legacyRecord(['image_position' => 'left', 'image_scale' => null], trashed: true);
    legacyRecord(['image_position' => null, 'image_scale' => null]);

    dropMigration()->up();

    // 小數位數的字串寫法隨資料庫不同（SQLite '1.5'、MySQL '1.50'），所以數值以浮點數比較
    $rows = array_map(
        fn ($row) => [...$row, 'image_scale' => $row['image_scale'] === null ? null : (float) $row['image_scale']],
        json_decode($files[BACKUP_PATH], true)['rows']
    );
    expect($rows)->toBe([
        ['id' => $a, 'image_position' => 'top', 'image_scale' => 1.5],
        ['id' => $b, 'image_position' => 'left', 'image_scale' => null],
    ])->and(legacyColumnsExist())->toBeFalse();
});

it('有紀錄但舊欄位都沒有值：寫出空備份再移除欄位', function () {
    $files = fakeBackupStorage();
    legacyRecord(['image_position' => null, 'image_scale' => null]);

    dropMigration()->up();

    expect(json_decode($files[BACKUP_PATH], true)['rows'])->toBe([])
        ->and(legacyColumnsExist())->toBeFalse();
});

it('全新資料庫（沒有任何紀錄）不備份，直接移除欄位', function () {
    $files = fakeBackupStorage();

    dropMigration()->up();

    expect(isset($files[BACKUP_PATH]))->toBeFalse()->and(legacyColumnsExist())->toBeFalse();
});

it('備份寫入失敗：丟例外、不刪欄位', function (Closure $put) {
    fakeBackupStorage(['putContent' => $put]);
    legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);

    expect(fn () => dropMigration()->up())->toThrow(RuntimeException::class)
        ->and(legacyColumnsExist())->toBeTrue();
})->with([
    '回傳 false' => [fn () => false],
    '丟例外' => [function () {
        throw new RuntimeException('S3 down');
    }],
]);

it('讀回內容與資料表不符：丟例外、不刪欄位', function () {
    fakeBackupStorage(['getContent' => fn () => json_encode(['rows' => []])]);
    legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);

    expect(fn () => dropMigration()->up())->toThrow(RuntimeException::class, '不符')
        ->and(legacyColumnsExist())->toBeTrue();
});

it('重跑：已有相同內容的備份視為成功；內容不同則中止、不覆蓋、不刪欄位', function () {
    $files = fakeBackupStorage();
    $id = legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);
    app(CaptureRecordImageDisplayBackup::class)->write();
    $first = $files[BACKUP_PATH];

    app(CaptureRecordImageDisplayBackup::class)->write(); // 相同內容再寫一次：成功
    expect($files[BACKUP_PATH])->toBe($first);

    DB::table('capture_records')->where('id', $id)->update(['image_position' => 'right']);
    expect(fn () => dropMigration()->up())->toThrow(RuntimeException::class, '已有不同內容')
        ->and($files[BACKUP_PATH])->toBe($first)
        ->and(legacyColumnsExist())->toBeTrue();
});

it('down：補回欄位並寫回備份的值（含軟刪除）；備份後新增的紀錄維持空', function () {
    fakeBackupStorage();
    $a = legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);
    $b = legacyRecord(['image_position' => 'bottom', 'image_scale' => 2.0], trashed: true);
    $migration = dropMigration();
    $migration->up();
    $later = CaptureRecord::factory()->create()->id;

    $migration->down();

    $row = fn (int $id) => DB::table('capture_records')->where('id', $id)->first();
    expect(legacyColumnsExist())->toBeTrue()
        ->and($row($a)->image_position)->toBe('top')
        ->and((float) $row($a)->image_scale)->toBe(1.5)
        ->and($row($b)->image_position)->toBe('bottom')
        ->and((float) $row($b)->image_scale)->toBe(2.0)
        ->and($row($later)->image_position)->toBeNull();
});

it('down 找不到或讀不到備份：丟例外並說明人工還原，資料表不變', function (?string $content) {
    $files = fakeBackupStorage();
    legacyRecord(['image_position' => 'top', 'image_scale' => 1.5]);
    $migration = dropMigration();
    $migration->up();
    if ($content === null) {
        unset($files[BACKUP_PATH]);
    } else {
        $files[BACKUP_PATH] = $content;
    }

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, '手動')
        ->and(legacyColumnsExist())->toBeFalse();
})->with([
    '備份檔不存在' => [null],
    '備份檔損壞' => ['{not json'],
]);
