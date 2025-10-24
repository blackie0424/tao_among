<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAudioFilenameToFishTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            // 新增主播放的音檔檔名欄位（可為 null）
            $table->string('audio_filename')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fish', function (Blueprint $table) {
            $table->dropColumn('audio_filename');
        });
    }
}
