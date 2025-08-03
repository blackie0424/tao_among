<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use \App\Models\FishAudio;
use \App\Models\FishNote;
use \App\Models\FishSize;
use \App\Models\Fish;

class FishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Fish::factory()
            ->count(10)
            ->has(FishNote::factory()->count(10), 'notes')
            ->has(FishSize::factory(), 'size')
            ->has(FishAudio::factory(), 'audio')
            ->create();
    }
}
