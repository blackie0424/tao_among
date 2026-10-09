<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        $name = '地名 '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'tribe' => null,
            'scope_key' => '',
            'name' => $name,
            'name_key' => mb_strtolower($name),
            'tao_name' => null,
            'notes' => null,
            'is_provisional' => false,
        ];
    }
}
