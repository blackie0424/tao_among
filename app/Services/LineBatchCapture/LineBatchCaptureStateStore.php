<?php

namespace App\Services\LineBatchCapture;

use Illuminate\Support\Facades\Cache;

class LineBatchCaptureStateStore
{
    public function getState(string $userId): ?string
    {
        return Cache::get($this->key($userId, 'state'));
    }

    public function putState(string $userId, string $state, int $minutes = 15): void
    {
        Cache::put($this->key($userId, 'state'), $state, now()->addMinutes($minutes));
    }

    public function getFishId(string $userId): ?int
    {
        $fishId = Cache::get($this->key($userId, 'fish'));

        return $fishId !== null ? (int) $fishId : null;
    }

    public function putFishId(string $userId, int $fishId, int $minutes = 15): void
    {
        Cache::put($this->key($userId, 'fish'), $fishId, now()->addMinutes($minutes));
    }

    /**
     * @return string[]
     */
    public function getImages(string $userId): array
    {
        return Cache::get($this->key($userId, 'images'), []);
    }

    /**
     * @param string[] $images
     */
    public function putImages(string $userId, array $images, int $minutes = 15): void
    {
        Cache::put($this->key($userId, 'images'), $images, now()->addMinutes($minutes));
    }

    /**
     * @return array<string, mixed>
     */
    public function getForm(string $userId): array
    {
        return Cache::get($this->key($userId, 'form'), []);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function putForm(string $userId, array $form, int $minutes = 15): void
    {
        Cache::put($this->key($userId, 'form'), $form, now()->addMinutes($minutes));
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateForm(string $userId, array $values, int $minutes = 15): void
    {
        $this->putForm($userId, array_merge($this->getForm($userId), $values), $minutes);
    }

    public function startSession(string $userId, int $fishId, int $minutes = 15): void
    {
        $this->putFishId($userId, $fishId, $minutes);
        $this->putImages($userId, [], $minutes);
        $this->putForm($userId, [], $minutes);
        $this->putState($userId, 'waiting_images', $minutes);
    }

    public function clear(string $userId): void
    {
        Cache::forget($this->key($userId, 'state'));
        Cache::forget($this->key($userId, 'fish'));
        Cache::forget($this->key($userId, 'images'));
        Cache::forget($this->key($userId, 'form'));
    }

    private function key(string $userId, string $suffix): string
    {
        return "line_user_{$userId}_batch_capture_{$suffix}";
    }
}
