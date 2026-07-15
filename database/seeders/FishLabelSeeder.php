<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FishLabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['group' => 'food_category', 'name' => 'oyod'],
            ['group' => 'food_category', 'name' => 'rahet'],
            ['group' => 'food_category', 'name' => '不分類'],
            ['group' => 'food_category', 'name' => '不食用'],
            ['group' => 'food_category', 'name' => '?'],
            ['group' => 'processing', 'name' => '去魚鱗'],
            ['group' => 'processing', 'name' => '不去魚鱗'],
            ['group' => 'processing', 'name' => '剝皮'],
            ['group' => 'processing', 'name' => '不食用'],
            ['group' => 'processing', 'name' => '?'],
        ];

        foreach ($labels as $label) {
            DB::table('fish_labels')->insertOrIgnore([
                'group' => $label['group'],
                'name' => $label['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
