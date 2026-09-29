<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $topics = [
            [
                'slug' => 'fish-guide',
                'title' => '魚類圖鑑',
                'image_path' => '',
                'sort_order' => 0,
                'is_published' => true,
                'is_fish_category' => true,
            ],
            [
                'slug' => 'fishing-method',
                'title' => '漁獵方法',
                'image_path' => '',
                'sort_order' => 1,
                'is_published' => false,
                'is_fish_category' => false,
            ],
            [
                'slug' => 'bait-guide',
                'title' => '魚餌圖鑑',
                'image_path' => '',
                'sort_order' => 2,
                'is_published' => false,
                'is_fish_category' => false,
            ],
            [
                'slug' => 'cooking',
                'title' => '烹調處理',
                'image_path' => '',
                'sort_order' => 3,
                'is_published' => false,
                'is_fish_category' => false,
            ],
        ];

        foreach ($topics as $topic) {
            DB::table('topics')->updateOrInsert(
                ['slug' => $topic['slug']],
                array_merge($topic, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('topics')->whereIn('slug', [
            'fish-guide',
            'fishing-method',
            'bait-guide',
            'cooking',
        ])->delete();
    }
};
