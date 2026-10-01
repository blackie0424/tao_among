<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function userRoleDefaultMigration(): object
{
    return require base_path('database/migrations/2026_10_01_000001_change_users_role_default_to_guest.php');
}

it('preserves every existing user role when changing the default to guest', function () {
    $migration = userRoleDefaultMigration();
    $migration->down();

    $users = collect(['guest', 'viewer', 'editor', 'admin'])->mapWithKeys(
        fn (string $role) => [$role => User::factory()->create(['role' => $role])]
    );

    $migration->up();

    $users->each(
        fn (User $user, string $role) => expect($user->fresh()->role)->toBe($role)
    );
});

it('defaults a user created without a role to guest', function () {
    $id = DB::table('users')->insertGetId([
        'name' => 'Default Role User',
        'source' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('users')->where('id', $id)->value('role'))->toBe('guest');
});

it('restores the admin default when rolling back', function () {
    $migration = userRoleDefaultMigration();
    $migration->down();

    $id = DB::table('users')->insertGetId([
        'name' => 'Rollback Default User',
        'source' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('users')->where('id', $id)->value('role'))->toBe('admin');

    $migration->up();
});
