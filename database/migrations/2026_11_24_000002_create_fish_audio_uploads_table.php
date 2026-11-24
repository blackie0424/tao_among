<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 已消耗的暫存檔紀錄：每個暫存檔（<使用者編號>:<暫存檔名>）只能確認成一筆錄音。
        // 唯一約束由資料庫擋下並發重送與跨魚重用；換檔、軟刪除之後這筆紀錄仍在，已用過的暫存檔不能再被確認。
        Schema::create('fish_audio_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('upload_key')->unique();
            $table->unsignedBigInteger('fish_audio_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_audio_uploads');
    }
};
