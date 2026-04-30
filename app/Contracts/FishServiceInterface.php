<?php

namespace App\Contracts;

use App\Models\Fish;

interface FishServiceInterface
{
    public function getAllFishes();

    public function getFishesBySince($since);

    public function getFishById($id);

    public function getFishByIdAndLocate($id, $locate);

    public function assignImageUrls($fishes);

    public function decorateFishMedia(Fish $fish): Fish;

    public function getFishDetails(int $id): array;
}
