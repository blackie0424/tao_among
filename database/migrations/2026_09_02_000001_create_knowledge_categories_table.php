<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_fish_category')->default(false)->comment('是否為魚類圖鑑分類');
            $table->string('slug')->unique()->comment('URL 識別符');
            $table->string('title')->comment('分類標題');
            $table->string('image_path')->comment('分類圖片路徑');
            $table->integer('sort_order')->default(0)->comment('排序順序');
            $table->boolean('is_published')->default(false)->comment('是否發布');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_categories');
    }
};
