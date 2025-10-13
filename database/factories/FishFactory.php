<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fish>
 */
class FishFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Fish ' . rand(1000, 9999),
            'image' => 'test-image-' . rand(1000, 9999) . '.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
