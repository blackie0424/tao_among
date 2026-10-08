<?php

namespace App\Services;

use App\Models\User;

class LocationVisibilityService
{
    private const RESTRICTED_KEYS = [
        'location', 'place', 'places', 'place_name', 'name_key', 'tao_name',
        'location_hint', 'capture_session', 'capture_sessions', 'legacy_sessions',
    ];

    public function __construct(private readonly PayloadFieldFilter $fieldFilter) {}

    public function filter(mixed $payload, ?User $user): mixed
    {
        return $this->filterForRole($payload, $user?->role);
    }

    public function filterForRole(mixed $payload, ?string $role): mixed
    {
        return User::roleCanAccessLocation($role)
            ? $payload
            : $this->fieldFilter->remove($payload, self::RESTRICTED_KEYS);
    }
}
