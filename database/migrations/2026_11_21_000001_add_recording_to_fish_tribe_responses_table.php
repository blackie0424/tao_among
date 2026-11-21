<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            $table->string('recording_filename', 64)->nullable()->after('name_submitted_at');
            // 目前這段錄音被送出的時間（微秒）；只有換成新錄音才更新，用來判斷「最早送出」
            $table->timestamp('recording_submitted_at', 6)->nullable()->after('recording_filename');
        });
    }

    public function down(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            $table->dropColumn(['recording_filename', 'recording_submitted_at']);
        });
    }
};
