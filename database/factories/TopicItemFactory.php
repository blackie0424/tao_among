<?php

namespace Database\Factories;

use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TopicItem> */
class TopicItemFactory extends Factory
{
    protected $model = TopicItem::class;

    public function definition(): array
    {
        return [
            'topic_id' => fn () => \App\Models\Topic::first()?->id ?? \App\Models\Topic::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function withImage(?string $path = null): static
    {
        $path ??= 'topic-items/'.fake()->uuid().'.jpg';

        return $this->afterCreating(function (TopicItem $item) use ($path): void {
            $item->media()->create([
                'type' => TopicItemMedia::TYPE_IMAGE,
                'source' => $path,
                'sort_order' => 0,
            ]);
        });
    }

    public function withoutImage(): static
    {
        return $this->state(fn () => []);
    }
}
