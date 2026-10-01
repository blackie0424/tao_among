<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds fish labels without creating fixed-credential users', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('fish_labels', 10);
    $this->assertDatabaseHas('fish_labels', [
        'group' => 'food_category',
        'name' => 'oyod',
    ]);
    $this->assertDatabaseHas('fish_labels', [
        'group' => 'processing',
        'name' => '去魚鱗',
    ]);
});
