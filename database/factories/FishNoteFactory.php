<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Fish;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FishNote>
 */
class FishNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fish_id'=> Fish::factory(), // 使用 FishFactory 生成隨機的 fish_id
            'note' => $this->faker->sentence(),
            'note_type' => $this->faker->randomElement(["外觀特徵", "分布地區", "傳統價值", "經驗分享", "相關故事", "游棲生態"]),
            'locate' => $this->faker->randomElement(['Iraraley', 'Yayo','Imorod','Iratay','Iranmeilek','Ivalino']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
