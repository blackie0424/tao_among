<?php

use App\Services\CaptureRecordImageDisplayBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 全新資料庫沒有任何紀錄，沒有東西可備份；有紀錄時一定先備份成功才刪欄位（失敗會丟例外中止）
        if (DB::table('capture_records')->exists()) {
            app(CaptureRecordImageDisplayBackup::class)->write();
        }

        Schema::table('capture_records', function (Blueprint $table) {
            $table->dropColumn(['image_position', 'image_scale']);
        });
    }

    public function down(): void
    {
        // 先確認備份讀得到；讀不到就丟例外，不動資料表
        $backup = app(CaptureRecordImageDisplayBackup::class);
        $rows = DB::table('capture_records')->exists() ? $backup->read() : [];

        Schema::table('capture_records', function (Blueprint $table) {
            $table->string('image_position')->nullable()->after('notes');
            $table->decimal('image_scale', 3, 2)->nullable()->after('image_position');
        });

        $backup->restore($rows);
    }
};
