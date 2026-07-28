<?php

namespace Database\Factories;

use App\Models\IntroCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntroCategory>
 */
class IntroCategoryFactory extends Factory
{
    protected $model = IntroCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
