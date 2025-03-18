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
            'name' => $this->faker->name,
            'type' => $this->faker->randomElement(['oyod', 'rahet']),
            'locate' => $this->faker->randomElement(['Iraraley', 'yayo']),
            'image' => $this->faker->unique()->regexify('[a-z0-9]{10}\.(png|jpg|jpeg)'),
            'process' => $this->faker->unique()->randomElement(['isisan', 'jingisisi','kolitan']),
            'created_at' => now(),
            'updated_at' => now(),

        ];
    }
}
