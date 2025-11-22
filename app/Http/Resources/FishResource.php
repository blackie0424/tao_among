<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FishResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $groupedClassifications = $this->tribalClassifications
            ->groupBy('food_category')
            ->map(function ($group) {
                return $group->pluck('tribe')->toArray();
            });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'audio_url' => $this->audio_url,
            'oyod' => implode(' ', $groupedClassifications->get('oyod', [])),
            'rahet' => implode(' ', $groupedClassifications->get('rahet', [])),
            'notEdible' => implode(' ', $groupedClassifications->get('不食用', [])),
            'uncertain' => implode(' ', $groupedClassifications->get('?', [])),
            'unclassified' => implode(' ', $groupedClassifications->get('不分類', [])),
        ];
    }
}
