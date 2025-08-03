<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Fish;

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
            'name' => "https://etycehppghhlxqpdvlga.supabase.co/storage/v1/object/public/tao_among_storage/audio/5cf5db69-0539-4f34-a60b-834be3f3fddf.webm",
            'locate' => $this->faker->randomElement(['iraraley', 'yayo','imorod','iratay','iranmeilek','ivalino']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }


}
