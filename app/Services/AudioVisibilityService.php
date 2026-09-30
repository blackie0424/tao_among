<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

class AudioVisibilityService
{
    private const RESTRICTED_KEYS = [
        'audio_url',
        'audio_duration',
        'audio_filename',
        'audios',
    ];

    public function filter(mixed $payload, ?User $user): mixed
    {
        if ($user?->canAccessAudio()) {
            return $payload;
        }

        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }

        return is_array($payload) ? $this->removeRestrictedFields($payload) : $payload;
    }

    private function removeRestrictedFields(array $payload): array
    {
        foreach (self::RESTRICTED_KEYS as $key) {
            unset($payload[$key]);
        }

        foreach ($payload as $key => $value) {
            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $payload[$key] = $this->removeRestrictedFields($value);
            }
        }

        return $payload;
    }
}
