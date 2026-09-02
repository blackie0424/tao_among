<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'is_fish_category' => true,
                'slug' => 'fish-guide',
                'title' => '魚類圖鑑',
                'image_path' => '', // 待後台上傳
                'sort_order' => 0,
                'is_published' => false,
            ],
            [
                'is_fish_category' => false,
                'slug' => 'bait',
                'title' => '魚餌',
                'image_path' => '', // 待後台上傳
                'sort_order' => 1,
                'is_published' => false,
            ],
            [
                'is_fish_category' => false,
                'slug' => 'fishing-method',
                'title' => '漁法',
                'image_path' => '', // 待後台上傳
                'sort_order' => 2,
                'is_published' => false,
            ],
            [
                'is_fish_category' => false,
                'slug' => 'cooking-method',
                'title' => '食用方式',
                'image_path' => '', // 待後台上傳
                'sort_order' => 3,
                'is_published' => false,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('knowledge_categories')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
