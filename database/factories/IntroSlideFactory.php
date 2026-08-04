<?php

namespace Database\Factories;

use App\Models\IntroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntroSlide>
 */
class IntroSlideFactory extends Factory
{
    protected $model = IntroSlide::class;

    public function definition(): array
    {
        return [
            'category_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->paragraph(),
            'media_type' => 'photo',
            'media_path' => null,
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function youtube(): static
    {
        return $this->state(fn () => [
            'media_type' => 'youtube',
            'media_path' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }
}
