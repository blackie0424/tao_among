<?php

namespace Database\Factories;

use App\Models\KnowledgeItem;
use App\Models\KnowledgeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeItem>
 */
class KnowledgeItemFactory extends Factory
{
    protected $model = KnowledgeItem::class;

    public function definition(): array
    {
        return [
            'knowledge_category_id' => function () {
                // 測試時應該已經有 Seeder 建立的分類,使用第一個
                return \App\Models\KnowledgeCategory::first()?->id ?? \App\Models\KnowledgeCategory::factory();
            },
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'image_path' => null,
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function withImage(): static
    {
        return $this->state(fn () => [
            'image_path' => 'knowledge-items/' . fake()->uuid() . '.jpg',
        ]);
    }
}
