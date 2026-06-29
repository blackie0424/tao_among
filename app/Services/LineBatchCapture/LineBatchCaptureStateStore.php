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

    /**
     * @return array{string, array<int, string>, int}|null [setId, indexedImages, total]
     */
    public function getIndexedImages(string $userId): ?array
    {
        $data = Cache::get($this->key($userId, 'indexed_images'));
        if ($data === null) {
            return null;
        }

        return [$data['set_id'], $data['indexed'], $data['total']];
    }

    /**
     * @param array{string, array<int, string>, int} $payload [setId, indexedImages, total]
     */
    public function putIndexedImages(string $userId, array $payload, int $minutes = 15): void
    {
        [$setId, $indexed, $total] = $payload;
        Cache::put($this->key($userId, 'indexed_images'), [
            'set_id'  => $setId,
            'indexed' => $indexed,
            'total'   => $total,
        ], now()->addMinutes($minutes));
    }

    public function forgetIndexedImages(string $userId): void
    {
        Cache::forget($this->key($userId, 'indexed_images'));
    }

    public function clear(string $userId): void
    {
        Cache::forget($this->key($userId, 'state'));
        Cache::forget($this->key($userId, 'fish'));
        Cache::forget($this->key($userId, 'images'));
        Cache::forget($this->key($userId, 'form'));
        Cache::forget($this->key($userId, 'indexed_images'));
    }

    private function key(string $userId, string $suffix): string
    {
        return "line_user_{$userId}_batch_capture_{$suffix}";
    }
}
