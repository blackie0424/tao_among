<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            // 新增圖鑑顯示圖片選擇欄位（參考到捕獲紀錄）
            $table->unsignedBigInteger('display_capture_record_id')
                  ->nullable()
                  ->after('audio_filename')
                  ->comment('使用者選擇的圖鑑展示圖片（參考捕獲紀錄）');
            
            // 外鍵約束：刪除捕獲紀錄時自動設為 NULL
            $table->foreign('display_capture_record_id')
                  ->references('id')
                  ->on('capture_records')
                  ->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            // 刪除外鍵約束
            $table->dropForeign(['display_capture_record_id']);
            // 刪除欄位
            $table->dropColumn('display_capture_record_id');
        });
    }
};
