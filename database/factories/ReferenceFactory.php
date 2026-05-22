<?php

namespace Database\Factories;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    protected $model = Reference::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'image_url' => fake()->optional()->imageUrl(),
            'external_url' => fake()->optional()->url(),
            'author' => fake()->name(),
            'status' => 'enabled',
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn () => ['status' => 'enabled']);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }
}

