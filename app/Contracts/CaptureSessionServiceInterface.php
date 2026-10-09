<?php

namespace App\Contracts;

interface CaptureSessionServiceInterface
{
    /**
     * 取得最近的捕獲資訊組合（排除 LINE Bot 資料）
     *
     * @return array<int, array{tribe: string, location: string, capture_method: string, capture_date: string, record_count: int}>
     */
    public function getRecentSessions(): array;

    /** @return array<int, array<string, mixed>> */
    public function getSelectableSessions(): array;

    /** @return array<int, array<string, mixed>> */
    public function getLegacyCombos(): array;
}
