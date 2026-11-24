<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Cache;

/**
 * LINE「新增發音」流程的暫存狀態（以使用者為 key，5 分鐘過期）。
 *
 * 流程綁定一隻魚：按「新增發音」時建立，之後選拼字、選部落、重新錄製的按鈕所帶的魚編號都必須與它一致，
 * 否則視為過期。多名稱的魚另外暫存「選單產生當下的拼字清單」，選取時要與目前的拆分結果完全一致。
 * 部落、錄音狀態沿用既有的快取鍵（audio_tribe、adding_audio、pending_audio_fish）。
 */
class LineAudioFlowState
{
    /** LINE 快速回覆按鈕數量上限 */
    public const MAX_CHOICES = 13;

    public const MESSAGE_EXPIRED = '❌ 這個按鈕已過期，請重新操作。';

    public const MESSAGE_NAME_CHANGED = '❌ 名稱已變更，請重新操作。';

    private const TTL_MINUTES = 5;

    /** 新流程開始：清掉前一次的所有暫存，綁定這隻魚；多名稱的魚暫存這次的拼字清單。 */
    public function begin(string $userId, int $fishId, array $names): void
    {
        $this->clear($userId);
        $this->put($userId, 'audio_fish', $fishId);
        if (count($names) > 1) {
            $this->put($userId, 'audio_choices', array_values($names));
        }
    }

    /** 這個按鈕所帶的魚編號是否就是目前流程的魚。 */
    public function owns(string $userId, int $fishId): bool
    {
        return (int) Cache::get($this->key($userId, 'audio_fish')) === $fishId && $fishId > 0;
    }

    /**
     * 使用者從選單選了第 $index 個拼字。
     *
     * @param  string[]  $currentNames  目前魚名的拆分結果
     * @return string 所選拼字
     *
     * @throws \DomainException 訊息就是要回給使用者的話（流程過期，或名稱已變更）
     */
    public function chooseName(string $userId, int $fishId, int $index, array $currentNames): string
    {
        $choices = Cache::get($this->key($userId, 'audio_choices'));
        if (! $this->owns($userId, $fishId) || ! is_array($choices) || ! isset($choices[$index])) {
            throw new \DomainException(self::MESSAGE_EXPIRED);
        }
        if (array_values($currentNames) !== $choices) {
            throw new \DomainException(self::MESSAGE_NAME_CHANGED);
        }
        $this->put($userId, 'audio_name', $choices[$index]);
        $this->touch($userId);

        return $choices[$index];
    }

    /** 可以錄音（或重錄）：流程是這隻魚、已選過部落，多名稱的魚也已選過拼字。 */
    public function canRecord(string $userId, int $fishId): bool
    {
        return $this->owns($userId, $fishId)
            && Cache::get($this->key($userId, 'audio_tribe')) !== null
            && (Cache::get($this->key($userId, 'audio_choices')) === null || $this->selectedName($userId) !== null);
    }

    public function selectedName(string $userId): ?string
    {
        return Cache::get($this->key($userId, 'audio_name'));
    }

    /** 流程往下走一步時延長期限（只延長還在的暫存）。 */
    public function touch(string $userId): void
    {
        foreach (['audio_fish', 'audio_choices', 'audio_name'] as $name) {
            $value = Cache::get($this->key($userId, $name));
            if ($value !== null) {
                $this->put($userId, $name, $value);
            }
        }
    }

    public function clear(string $userId): void
    {
        foreach (['audio_fish', 'audio_choices', 'audio_name', 'audio_tribe', 'adding_audio', 'pending_audio_fish'] as $name) {
            Cache::forget($this->key($userId, $name));
        }
    }

    private function put(string $userId, string $name, mixed $value): void
    {
        Cache::put($this->key($userId, $name), $value, now()->addMinutes(self::TTL_MINUTES));
    }

    private function key(string $userId, string $name): string
    {
        return "line_user_{$userId}_{$name}";
    }
}
