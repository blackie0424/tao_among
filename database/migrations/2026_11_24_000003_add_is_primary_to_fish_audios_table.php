<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            // 這段錄音是它所屬拼字（spoken_name）的主要發音；同一隻魚同一個拼字最多一段為真，由 FishAudioRecordingService 維護。
            // 全部為假時，魚頁播該拼字最早的一段。
            $table->boolean('is_primary')->default(false)->after('spoken_name');
        });
    }

    public function down(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
