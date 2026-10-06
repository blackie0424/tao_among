<?php

namespace App\Services;

use App\Models\User;

class LocationVisibilityService
{
    private const RESTRICTED_KEYS = ['location'];

    public function __construct(private readonly PayloadFieldFilter $fieldFilter)
    {
    }

    public function filter(mixed $payload, ?User $user): mixed
    {
        return $user?->canAccessLocation()
            ? $payload
            : $this->fieldFilter->remove($payload, self::RESTRICTED_KEYS);
    }
}