<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $classifications = DB::table('tribal_classifications')
            ->whereNull('deleted_at')
            ->whereRaw("(food_category != '' OR processing_method != '')")
            ->get();

        foreach ($classifications as $tc) {
            if (!empty($tc->food_category)) {
                DB::table('fish_labels')->insertOrIgnore([
                    'group' => 'food_category',
                    'name' => $tc->food_category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $label = DB::table('fish_labels')
                    ->where('group', 'food_category')
                    ->where('name', $tc->food_category)
                    ->first();

                DB::table('tribal_classification_labels')->insertOrIgnore([
                    'tribal_classification_id' => $tc->id,
                    'fish_label_id' => $label->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!empty($tc->processing_method)) {
                DB::table('fish_labels')->insertOrIgnore([
                    'group' => 'processing',
                    'name' => $tc->processing_method,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $label = DB::table('fish_labels')
                    ->where('group', 'processing')
                    ->where('name', $tc->processing_method)
                    ->first();

                DB::table('tribal_classification_labels')->insertOrIgnore([
                    'tribal_classification_id' => $tc->id,
                    'fish_label_id' => $label->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tribal_classification_labels')->delete();
        DB::table('fish_labels')->delete();
    }
};
