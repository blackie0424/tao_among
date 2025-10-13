<?php

namespace Database\Factories;

use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaptureRecord>
 */
class CaptureRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fish_id' => Fish::factory(),
            'image_path' => 'test-image-' . rand(1000, 9999) . '.jpg',
            'tribe' => collect(['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'])->random(),
            'location' => 'Test Location ' . rand(1, 100),
            'capture_method' => collect(['網捕', '釣魚', '陷阱', '徒手捕捉', '魚叉'])->random(),
            'capture_date' => now()->subDays(rand(1, 365))->format('Y-m-d'),
            'notes' => 'Test capture notes ' . rand(1000, 9999),
        ];
    }

    /**
     * Indicate that the capture record is for a specific tribe.
     */
    public function forTribe(string $tribe): static
    {
        return $this->state(fn (array $attributes) => [
            'tribe' => $tribe,
        ]);
    }

    /**
     * Indicate that the capture record is from a specific location.
     */
    public function atLocation(string $location): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $location,
        ]);
    }

    /**
     * Indicate that the capture record uses a specific method.
     */
    public function withMethod(string $method): static
    {
        return $this->state(fn (array $attributes) => [
            'capture_method' => $method,
        ]);
    }

    /**
     * Indicate that the capture record is from today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'capture_date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the capture record has no notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => null,
        ]);
    }
}
