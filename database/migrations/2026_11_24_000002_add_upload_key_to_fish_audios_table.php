<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            // 網頁錄音確認時的「<使用者編號>:<暫存檔名>」；唯一約束讓同一個暫存檔只能被確認成一筆紀錄
            // （並發重送、跨魚重用都由資料庫擋下）。舊資料與 LINE 錄音為空，空值不受唯一約束影響。
            $table->string('upload_key')->nullable()->unique()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            $table->dropUnique(['upload_key']);
            $table->dropColumn('upload_key');
        });
    }
};
