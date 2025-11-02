<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class PurgeService
{
    public function __construct(
        private readonly SupabaseStorageService $storage
    ) {}

    /**
     * Purge pending files older than given unix timestamp.
     */
    public function purgePendingOlderThan(int $olderThanTs): int
    {
        // NOTE: Supabase public API listing may require RLS or service role; implement when listing endpoint available.
        // Placeholder: return 0 and rely on bucket lifecycle rules if configured.
        return 0;
    }
}
