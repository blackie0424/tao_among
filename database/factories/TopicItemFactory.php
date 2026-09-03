<?php

namespace Database\Factories;

use App\Models\TopicItem;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopicItem>
 */
class TopicItemFactory extends Factory
{
    protected $model = TopicItem::class;

    public function definition(): array
    {
        return [
            'topic_id' => function () {
                // 測試時應該已經有 Seeder 建立的主題,使用第一個
                return \App\Models\Topic::first()?->id ?? \App\Models\Topic::factory();
            },
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'image_path' => 'topic-items/' . fake()->uuid() . '.jpg',
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function withoutImage(): static
    {
        return $this->state(fn () => [
            'image_path' => null,
        ]);
    }
}
