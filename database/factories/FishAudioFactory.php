<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FishAudio>
 */
class FishAudioFactory extends Factory
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
            'audioUrl' => $this->faker->sentence(),
            'locate' => $this->faker->randomElement(['iraraley', 'yayo','imorod','iratay','iranmeilek','ivalino']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }


}
