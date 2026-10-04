<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_item_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_item_id')
                ->constrained('topic_items')
                ->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('source', 2048);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['topic_item_id', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_item_media');
    }
};
