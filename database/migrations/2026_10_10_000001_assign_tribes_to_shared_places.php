<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $isBlank = static fn (?string $value): bool => $value === null
                || preg_replace('/[\s\x{3000}]+/u', '', $value) === '';

            foreach (DB::table('places')->whereNull('tribe')->orderBy('id')->get() as $place) {
                $tribes = DB::table('capture_sessions')->where('place_id', $place->id)
                    ->distinct()->pluck('tribe')->all();
                sort($tribes, SORT_STRING);
                $reused = false;

                foreach ($tribes as $tribe) {
                    $sessionIds = DB::table('capture_sessions')->where('place_id', $place->id)
                        ->where('tribe', $tribe)->pluck('id');
                    $target = DB::table('places')->where('scope_key', $tribe)
                        ->where('name_key', $place->name_key)->first();
                    $targetName = $place->name;

                    if ($target) {
                        $targetId = $target->id;
                        $targetName = $target->name;
                        $details = [];
                        foreach (['tao_name', 'notes'] as $field) {
                            if ($isBlank($target->$field) && ! $isBlank($place->$field)) {
                                $details[$field] = $place->$field;
                            }
                        }
                        if ($details !== []) {
                            DB::table('places')->where('id', $targetId)->update($details);
                        }
                    } elseif (! $reused) {
                        $targetId = $place->id;
                        DB::table('places')->where('id', $place->id)->update([
                            'tribe' => $tribe, 'scope_key' => $tribe,
                        ]);
                        $reused = true;
                    } else {
                        $targetId = DB::table('places')->insertGetId([
                            'tribe' => $tribe,
                            'scope_key' => $tribe,
                            'name' => $place->name,
                            'name_key' => $place->name_key,
                            'tao_name' => $place->tao_name,
                            'notes' => $place->notes,
                            'is_provisional' => $place->is_provisional,
                            'created_at' => $place->created_at,
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('capture_sessions')->whereIn('id', $sessionIds)->update(['place_id' => $targetId]);
                    // Query builder includes soft-deleted capture records.
                    DB::table('capture_records')->whereIn('session_id', $sessionIds)->update(['location' => $targetName]);
                }

                if (! $reused) {
                    DB::table('places')->where('id', $place->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible: split places cannot be reliably identified as one original place.
    }
};
