<?php

namespace App\Services;

use App\Models\User;

class AudioVisibilityService
{
    private const RESTRICTED_KEYS = [
        'audio_url',
        'audio_duration',
        'audio_filename',
        'audios',
    ];

    public function __construct(private readonly PayloadFieldFilter $fieldFilter)
    {
    }

    public function filter(mixed $payload, ?User $user): mixed
    {
        if ($user?->canAccessAudio()) {
            return $payload;
        }

        return $this->fieldFilter->remove($payload, self::RESTRICTED_KEYS);
    }
}
