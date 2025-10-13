<?php

namespace Database\Factories;

use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TribalClassification>
 */
class TribalClassificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fish_id' => Fish::factory(),
            'tribe' => collect(['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'])->random(),
            'food_category' => collect(['oyod', 'rahet', '不分類', '不食用', '?', ''])->random(),
            'processing_method' => collect(['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''])->random(),
            'notes' => rand(0, 10) > 3 ? 'Test tribal notes ' . rand(1000, 9999) : null,
        ];
    }

    /**
     * Indicate that the tribal classification is for a specific tribe.
     */
    public function forTribe(string $tribe): static
    {
        return $this->state(fn (array $attributes) => [
            'tribe' => $tribe,
        ]);
    }

    /**
     * Indicate that the tribal classification has a specific food category.
     */
    public function withFoodCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'food_category' => $category,
        ]);
    }

    /**
     * Indicate that the tribal classification has a specific processing method.
     */
    public function withProcessingMethod(string $method): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_method' => $method,
        ]);
    }

    /**
     * Indicate that the tribal classification has no notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => null,
        ]);
    }

    /**
     * Indicate that the tribal classification has empty string values.
     */
    public function withEmptyValues(): static
    {
        return $this->state(fn (array $attributes) => [
            'food_category' => '',
            'processing_method' => '',
        ]);
    }
}
