<?php

namespace Database\Factories;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        // Note: Topic 是固定的 4 筆資料,通常不應該使用 factory
        // 測試中應該直接使用 Seeder
        return [
            'is_fish_category' => false,
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'image_path' => null,
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function fishCategory(): static
    {
        return $this->state(fn () => ['is_fish_category' => true]);
    }
}
