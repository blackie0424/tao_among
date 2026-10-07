<?php

use App\Models\User;
use App\Services\LocationVisibilityService;

it('denies location access when the role is null', function () {
    expect(User::roleCanAccessLocation(null))->toBeFalse();
});

it('removes location when filtering for a null role', function () {
    $payload = ['location' => 'ZZLOCATIONMARK', 'tribe' => 'ivalino'];

    expect(app(LocationVisibilityService::class)->filterForRole($payload, null))
        ->toBe(['tribe' => 'ivalino']);
});
