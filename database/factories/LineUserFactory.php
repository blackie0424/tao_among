<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LineUser>
 */
class LineUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'line_user_id' => 'U' . $this->faker->unique()->regexify('[A-Za-z0-9]{32}'),
            'display_name' => $this->faker->name(),
            'picture_url' => $this->faker->optional()->imageUrl(),
            'role' => 'viewer',
        ];
    }
}
