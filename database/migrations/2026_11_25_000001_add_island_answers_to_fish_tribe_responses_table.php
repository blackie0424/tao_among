<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            // 魚島作答的處理方式（選項見 fish_options.response_processing_methods）；among 魚頁不寫
            $table->string('processing_method', 32)->nullable()->after('food_category');
            // 回答者自己的部落：在魚島送出時記下角色的部落；只在 among 魚頁答過的沒有值
            $table->string('respondent_tribe', 32)->nullable()->after('processing_method');
        });
    }

    public function down(): void
    {
        Schema::table('fish_tribe_responses', function (Blueprint $table): void {
            $table->dropColumn(['processing_method', 'respondent_tribe']);
        });
    }
};
