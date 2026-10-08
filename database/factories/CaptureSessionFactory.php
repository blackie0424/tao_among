<?php

namespace Database\Factories;

use App\Models\CaptureSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaptureSessionFactory extends Factory
{
    protected $model = CaptureSession::class;

    public function definition(): array
    {
        return [
            'capture_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'tribe' => 'ivalino',
            'capture_method' => '釣魚',
            'place_id' => null,
            'location_hint' => null,
            'notes' => null,
        ];
    }
}
