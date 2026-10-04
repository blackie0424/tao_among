<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('topic_items')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->each(function (object $item) use ($now): void {
                $alreadyMigrated = DB::table('topic_item_media')
                    ->where('topic_item_id', $item->id)
                    ->where('type', 'image')
                    ->where('source', $item->image_path)
                    ->exists();

                if (! $alreadyMigrated) {
                    DB::table('topic_item_media')->insert([
                        'topic_item_id' => $item->id,
                        'type' => 'image',
                        'source' => $item->image_path,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        Schema::table('topic_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('topic_items', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('title');
        });

        DB::table('topic_items')->orderBy('id')->each(function (object $item): void {
            $imagePath = DB::table('topic_item_media')
                ->where('topic_item_id', $item->id)
                ->where('type', 'image')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('source');

            DB::table('topic_items')->where('id', $item->id)->update([
                'image_path' => $imagePath,
            ]);
        });
    }
};
