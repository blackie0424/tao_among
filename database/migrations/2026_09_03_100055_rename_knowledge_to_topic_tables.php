<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename knowledge_categories to topics
        Schema::rename('knowledge_categories', 'topics');
        
        // Rename knowledge_items to topic_items and update foreign key column
        Schema::rename('knowledge_items', 'topic_items');
        
        // Rename the foreign key column
        Schema::table('topic_items', function (Blueprint $table) {
            $table->renameColumn('knowledge_category_id', 'topic_id');
        });
    }

    public function down(): void
    {
        // Rename back the foreign key column first
        Schema::table('topic_items', function (Blueprint $table) {
            $table->renameColumn('topic_id', 'knowledge_category_id');
        });
        
        // Rename tables back
        Schema::rename('topic_items', 'knowledge_items');
        Schema::rename('topics', 'knowledge_categories');
    }
};
