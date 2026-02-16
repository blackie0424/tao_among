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
        Schema::table('fish_audios', function (Blueprint $table) {
            // 新增音檔時長欄位（毫秒）
            $table->integer('duration')->nullable()->after('locate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
