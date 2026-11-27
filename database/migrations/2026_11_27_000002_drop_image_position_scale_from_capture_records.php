<?php

use App\Services\CaptureRecordImageDisplayBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 一定先備份成功（沒有舊值也寫空備份）才刪欄位；備份失敗會丟例外中止
        app(CaptureRecordImageDisplayBackup::class)->write();

        Schema::table('capture_records', function (Blueprint $table) {
            $table->dropColumn(['image_position', 'image_scale']);
        });
    }

    public function down(): void
    {
        // 先驗證備份（存在、可解析、每筆結構合法）；任何一項不過就丟例外，不動資料表
        $backup = app(CaptureRecordImageDisplayBackup::class);
        $rows = $backup->read();

        Schema::table('capture_records', function (Blueprint $table) {
            $table->string('image_position')->nullable()->after('notes');
            $table->decimal('image_scale', 3, 2)->nullable()->after('image_position');
        });

        $backup->restore($rows);
    }
};
