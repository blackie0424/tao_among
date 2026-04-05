<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'source'            => 'web',
            'role'              => 'admin',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'web',
            'role'   => 'admin',
        ]);
    }

    public function lineEditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'         => fake()->name(),
            'email'        => null,
            'password'     => null,
            'source'       => 'line',
            'role'         => 'editor',
            'line_user_id' => 'U' . fake()->unique()->regexify('[A-Za-z0-9]{32}'),
            'picture_url'  => fake()->optional()->imageUrl(),
        ]);
    }

    public function lineViewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'         => fake()->name(),
            'email'        => null,
            'password'     => null,
            'source'       => 'line',
            'role'         => 'viewer',
            'line_user_id' => 'U' . fake()->unique()->regexify('[A-Za-z0-9]{32}'),
            'picture_url'  => fake()->optional()->imageUrl(),
        ]);
    }
}
