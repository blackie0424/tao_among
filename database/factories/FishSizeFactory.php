<?php

namespace Database\Factories;

use \App\Models\Fish;
use App\Models\FishSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FishSize>
 */
class FishSizeFactory extends Factory
{
    protected $model = FishSize::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $allParts = [
            "手指1", "手指2", "半掌1", "半掌2", "手掌",
            "下臂1", "下臂2", "下臂3", "下臂4", "下臂5", "下臂6", "下臂7",
            "上臂1", "上臂2", "上臂3", "上臂4", "肩膀"
        ];

        $parts = $this->faker->randomElements($allParts, rand(6, count($allParts)));


        return [
            'fish_id' => Fish::factory(),
            'parts' => json_encode($parts),
        ];
    }
}
