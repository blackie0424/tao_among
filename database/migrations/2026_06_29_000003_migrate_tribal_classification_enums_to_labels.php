<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $foodCategoryGroup = 'food_category';
        $processingGroup = 'processing';

        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?'];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?'];

        foreach ($foodCategories as $name) {
            DB::table('fish_labels')->insertOrIgnore([
                'group' => $foodCategoryGroup,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($processingMethods as $name) {
            DB::table('fish_labels')->insertOrIgnore([
                'group' => $processingGroup,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $classifications = DB::table('tribal_classifications')
            ->whereNull('deleted_at')
            ->whereRaw("(food_category != '' OR processing_method != '')")
            ->get();

        foreach ($classifications as $tc) {
            if (!empty($tc->food_category)) {
                $label = DB::table('fish_labels')
                    ->where('group', $foodCategoryGroup)
                    ->where('name', $tc->food_category)
                    ->first();

                if ($label) {
                    DB::table('tribal_classification_labels')->insertOrIgnore([
                        'tribal_classification_id' => $tc->id,
                        'fish_label_id' => $label->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (!empty($tc->processing_method)) {
                $label = DB::table('fish_labels')
                    ->where('group', $processingGroup)
                    ->where('name', $tc->processing_method)
                    ->first();

                if ($label) {
                    DB::table('tribal_classification_labels')->insertOrIgnore([
                        'tribal_classification_id' => $tc->id,
                        'fish_label_id' => $label->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('tribal_classification_labels')->delete();
        DB::table('fish_labels')->delete();
    }
};
