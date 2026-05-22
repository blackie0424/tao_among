<?php

namespace Database\Factories;

use App\Models\Fish;
use App\Models\Reference;
use App\Models\ReferenceKnowledge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceKnowledge>
 */
class ReferenceKnowledgeFactory extends Factory
{
    protected $model = ReferenceKnowledge::class;

    public function definition(): array
    {
        return [
            'fish_id' => Fish::factory(),
            'reference_id' => Reference::factory(),
            'tribe' => fake()->optional()->randomElement(config('fish_options.tribes')),
            'content' => fake()->paragraph(),
            'pages' => '10-12',
            'page_start' => 10,
            'page_end' => 12,
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory()->admin(),
        ];
    }
}
