<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_category_id')
                ->constrained('knowledge_categories')
                ->onDelete('cascade')
                ->comment('所屬知識分類');
            $table->string('title')->comment('項目標題');
            $table->string('image_path')->nullable()->comment('項目圖片路徑');
            $table->text('description')->nullable()->comment('項目說明');
            $table->integer('sort_order')->default(0)->comment('排序順序');
            $table->boolean('is_published')->default(false)->comment('是否發布');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
