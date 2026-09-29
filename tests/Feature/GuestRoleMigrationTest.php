<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guestRoleMigration(): object
{
    return require base_path('database/migrations/2026_09_29_000002_add_guest_to_user_roles.php');
}

it('preserves every existing role when adding guest', function () {
    $migration = guestRoleMigration();
    $migration->down();

    $users = collect(['viewer', 'editor', 'admin'])->mapWithKeys(
        fn (string $role) => [$role => User::factory()->create(['role' => $role])]
    );

    $migration->up();

    $users->each(
        fn (User $user, string $role) => expect($user->fresh()->role)->toBe($role)
    );
});

it('supports guest and converts it to viewer when rolling back', function () {
    $user = User::factory()->create(['role' => 'guest']);
    $migration = guestRoleMigration();

    $migration->down();

    expect($user->fresh()->role)->toBe('viewer');

    $migration->up();
});
