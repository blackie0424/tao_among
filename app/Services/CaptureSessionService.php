<?php

namespace App\Services;

use App\Contracts\CaptureSessionServiceInterface;
use App\Models\CaptureRecord;

class CaptureSessionService implements CaptureSessionServiceInterface
{
    /**
     * 取得最近的捕獲資訊組合（排除 LINE Bot 資料）
     *
     * @return array<int, array{tribe: string, location: string, capture_method: string, capture_date: string, record_count: int}>
     */
    public function getRecentSessions(): array
    {
        return CaptureRecord::selectRaw(
            'tribe, location, capture_method, capture_date, COUNT(*) as record_count'
        )
            ->where('location', '!=', 'LINE Bot')
            ->groupBy('tribe', 'location', 'capture_method', 'capture_date')
            ->orderByDesc('capture_date')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'tribe'          => $row->tribe,
                'location'       => $row->location,
                'capture_method' => $row->capture_method,
                'capture_date'   => $row->capture_date->format('Y-m-d'),
                'record_count'   => (int) $row->record_count,
            ])
            ->values()
            ->all();
    }
}
