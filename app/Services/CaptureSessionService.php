<?php

namespace App\Services;

use App\Contracts\CaptureSessionServiceInterface;
use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureSessionService implements CaptureSessionServiceInterface
{
    public function __construct(private readonly PlaceService $placeService) {}

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

    /** @return array<int, array<string, mixed>> */
    public function getSelectableSessions(): array
    {
        return CaptureSession::query()
            ->with('place')
            ->withCount('captureRecords')
            ->orderByDesc('capture_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (CaptureSession $session) => [
                'id' => $session->id,
                'capture_date' => $session->capture_date->format('Y-m-d'),
                'tribe' => $session->tribe,
                'capture_method' => $session->capture_method,
                'place_name' => $session->place?->name,
                'location_hint' => $session->location_hint,
                'record_count' => (int) $session->capture_records_count,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function getLegacyCombos(): array
    {
        $records = CaptureRecord::query()
            ->whereNull('session_id')
            ->where(fn ($query) => $query->whereNull('location')->orWhere('location', '!=', 'LINE Bot'))
            ->orderByDesc('capture_date')
            ->orderByDesc('id')
            ->get(['id', 'capture_date', 'tribe', 'capture_method', 'location']);

        $combos = [];
        foreach ($records as $record) {
            $location = $this->normalizeLegacyLocation($record->location);
            $combo = [
                'capture_date' => $record->capture_date->format('Y-m-d'),
                'tribe' => $record->tribe,
                'capture_method' => $record->capture_method,
                'location' => $location['display'],
            ];
            $key = $this->comboKey($combo, $location['key']);

            if (! isset($combos[$key])) {
                $combos[$key] = [...$combo, 'record_count' => 0, '_location_key' => $location['key']];
            }
            $combos[$key]['record_count']++;
        }

        if ($combos === []) {
            return [];
        }

        $dates = array_values(array_unique(array_column($combos, 'capture_date')));
        $existing = CaptureSession::query()
            ->with('place')
            ->whereBetween('capture_date', [min($dates).' 00:00:00', max($dates).' 23:59:59'])
            ->get()
            ->mapWithKeys(fn (CaptureSession $session) => [$this->sessionKey($session) => true])
            ->all();

        return collect($combos)
            ->reject(fn (array $combo, string $key) => isset($existing[$key]))
            ->take(20)
            ->map(function (array $combo): array {
                unset($combo['_location_key']);

                return $combo;
            })
            ->values()
            ->all();
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

    /** @param array{capture_date:string,tribe:string,capture_method:string,location?:?string}|null $legacyCombo */
    public function resolveForCreate(?int $sessionId, ?array $legacyCombo): CaptureSession
    {
        if ($sessionId !== null) {
            $session = CaptureSession::query()->lockForUpdate()->find($sessionId);
            if (! $session) {
                throw ValidationException::withMessages(['session_id' => '這個情境已不存在，請重新選擇']);
            }

            return $session;
        }

        if ($legacyCombo === null) {
            throw ValidationException::withMessages(['session_id' => '請選擇情境']);
        }

        $location = $this->normalizeLegacyLocation($legacyCombo['location'] ?? null);
        $combo = [
            'capture_date' => $legacyCombo['capture_date'],
            'tribe' => $legacyCombo['tribe'],
            'capture_method' => $legacyCombo['capture_method'],
            'location' => $location['display'],
        ];
        $key = $this->comboKey($combo, $location['key']);

        $equivalent = CaptureSession::query()
            ->with('place')
            ->whereDate('capture_date', $combo['capture_date'])
            ->where('tribe', $combo['tribe'])
            ->where('capture_method', $combo['capture_method'])
            ->lockForUpdate()
            ->get()
            ->first(fn (CaptureSession $session) => $this->sessionKey($session) === $key);

        if ($equivalent) {
            return $equivalent;
        }

        $legacyExists = CaptureRecord::query()
            ->whereNull('session_id')
            ->whereDate('capture_date', $combo['capture_date'])
            ->where('tribe', $combo['tribe'])
            ->where('capture_method', $combo['capture_method'])
            ->where(fn ($query) => $query->whereNull('location')->orWhere('location', '!=', 'LINE Bot'))
            ->lockForUpdate()
            ->get(['location'])
            ->contains(fn (CaptureRecord $record) => $this->normalizeLegacyLocation($record->location)['key'] === $location['key']);

        if (! $legacyExists) {
            throw ValidationException::withMessages(['legacy_combo' => '這組舊資料已不存在，請重新選擇']);
        }

        $place = $location['key'] === null ? null : Place::query()->where('name_key', $location['key'])->first();

        return CaptureSession::create([
            'capture_date' => $combo['capture_date'],
            'tribe' => $combo['tribe'],
            'capture_method' => $combo['capture_method'],
            'place_id' => $place?->id,
            'location_hint' => $place ? null : $location['display'],
        ]);
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

    /** @return array{display:?string,key:?string} */
    private function normalizeLegacyLocation(?string $location): array
    {
        if ($location === null) {
            return ['display' => null, 'key' => null];
        }

        $normalized = $this->placeService->normalize($location);
        if ($normalized['name'] === '' || $normalized['name'] === '待補充') {
            return ['display' => null, 'key' => null];
        }

        return ['display' => $normalized['name'], 'key' => $normalized['name_key']];
    }

    private function comboKey(array $combo, ?string $locationKey): string
    {
        return implode('|', [
            $combo['capture_date'],
            $combo['tribe'],
            $combo['capture_method'],
            $locationKey ?? '<null>',
        ]);
    }

    private function sessionKey(CaptureSession $session): string
    {
        $location = $session->place
            ? ['key' => $session->place->name_key]
            : $this->normalizeLegacyLocation($session->location_hint);

        return $this->comboKey([
            'capture_date' => $session->capture_date->format('Y-m-d'),
            'tribe' => $session->tribe,
            'capture_method' => $session->capture_method,
        ], $location['key']);
    }
}
