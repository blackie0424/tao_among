<?php

namespace App\Services;

use App\Contracts\CaptureSessionServiceInterface;
use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureSessionService implements CaptureSessionServiceInterface
{
    /**
     * @return array<int, array{tribe: string, location: string, capture_method: string, capture_date: string, record_count: int}>
     */
    public function getRecentSessions(): array
    {
        return CaptureRecord::selectRaw('tribe, location, capture_method, capture_date, COUNT(*) as record_count')
            ->where('location', '!=', 'LINE Bot')
            ->groupBy('tribe', 'location', 'capture_method', 'capture_date')
            ->orderByDesc('capture_date')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'tribe' => $row->tribe,
                'location' => $row->location,
                'capture_method' => $row->capture_method,
                'capture_date' => $row->capture_date->format('Y-m-d'),
                'record_count' => (int) $row->record_count,
            ])->values()->all();
    }

    /** @return array{tribe:string,capture_method:string,capture_date:string,location:?string} */
    public function recordAttributes(CaptureSession $session): array
    {
        $session->loadMissing('place');

        return [
            'tribe' => $session->tribe,
            'capture_method' => $session->capture_method,
            'capture_date' => $session->capture_date->format('Y-m-d'),
            'location' => $session->place?->name ?: ($session->location_hint ?: null),
        ];
    }

    public function update(CaptureSession $session, array $data): CaptureSession
    {
        return DB::transaction(function () use ($session, $data): CaptureSession {
            $session->update($data);
            $attributes = $this->recordAttributes($session->refresh());
            CaptureRecord::withTrashed()->where('session_id', $session->id)->update($attributes);

            return $session->refresh();
        });
    }

    public function delete(CaptureSession $session): void
    {
        if (CaptureRecord::withTrashed()->where('session_id', $session->id)->exists()) {
            throw ValidationException::withMessages(['session' => '此情境已有紀錄（含已刪除），不能刪除']);
        }

        $session->delete();
    }
}
