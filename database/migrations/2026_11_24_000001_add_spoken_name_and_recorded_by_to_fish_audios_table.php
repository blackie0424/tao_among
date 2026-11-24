<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            // 這段錄音錄的是魚名拆分後的哪個名稱；空值＝未對應名稱
            $table->string('spoken_name')->nullable()->after('locate');
            // 錄音者（users.id）；舊資料與 LINE 查無帳號時為空
            $table->unsignedBigInteger('recorded_by')->nullable()->after('spoken_name');
            $table->index(['fish_id', 'spoken_name']);
            // 未標部落以空值表示（原本 NOT NULL，舊資料修正要把非部落的值清成空）
            $table->string('locate')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fish_audios', function (Blueprint $table) {
            $table->dropIndex(['fish_id', 'spoken_name']);
            $table->dropColumn(['spoken_name', 'recorded_by']);
            $table->string('locate')->nullable(false)->change();
        });
    }
};
