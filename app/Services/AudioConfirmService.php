<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AudioConfirmService
{
    public function __construct(
        private readonly SupabaseStorageService $storage
    ) {}

    /**
     * Move pending object to final path and persist DB records in a transaction.
     * Throws Exception on failure; caller is responsible for compensation delete.
     */
    public function moveAndPersist(Fish $fish, string $pendingPath, string $finalPath, string $finalName): void
    {
        DB::beginTransaction();
        try {
            $moved = $this->storage->moveObject($pendingPath, $finalPath);
            if (!$moved) {
                throw new Exception('Failed to move object');
            }

            // Upsert FishAudio
            FishAudio::firstOrCreate([
                'fish_id' => $fish->id,
                'name' => $finalName,
            ], [
                'locate' => 'supabase',
            ]);

            // Update fish main audio if empty
            if (empty($fish->audio_filename)) {
                $fish->audio_filename = $finalName;
                $fish->save();
            }

            DB::commit();
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('[audio.confirm.service] move/persist failed', [
                'fish_id' => $fish->id,
                'pendingPath' => $pendingPath,
                'finalPath' => $finalPath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
