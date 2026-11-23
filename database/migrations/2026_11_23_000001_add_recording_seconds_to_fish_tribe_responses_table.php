<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            // 錄音時前端記下的秒數（估計值，1～30）；修訂前存下的舊錄音沒有值
            $table->unsignedTinyInteger('recording_seconds')->nullable()->after('recording_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            $table->dropColumn('recording_seconds');
        });
    }
};
