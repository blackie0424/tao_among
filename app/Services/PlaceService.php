<?php

namespace App\Services;

use App\Models\CaptureRecord;
use App\Models\CaptureSession;
use App\Models\Place;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceService
{
    public static function scopeKey(?string $tribe): string
    {
        return $tribe ?? '';
    }

    /** @return array{name:string,name_key:string} */
    public function normalize(string $name): array
    {
        $display = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', $name));

        return ['name' => $display, 'name_key' => mb_strtolower($display)];
    }

    public function findForTribe(string $nameKey, string $tribe): ?Place
    {
        return Place::query()
            ->where('name_key', $nameKey)
            ->whereIn('scope_key', [self::scopeKey($tribe), ''])
            ->orderByRaw('CASE WHEN scope_key = ? THEN 0 ELSE 1 END', [self::scopeKey($tribe)])
            ->orderBy('id')
            ->first();
    }

    public function create(array $data, bool $provisional): Place
    {
        $normalized = $this->normalize($data['name']);
        $attributes = [
            ...$normalized,
            'tribe' => $data['tribe'] ?? null,
            'scope_key' => self::scopeKey($data['tribe'] ?? null),
            'tao_name' => $data['tao_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_provisional' => $provisional,
        ];

        return Place::create($attributes);
    }

    public function resolveName(string $name, string $tribe): ?Place
    {
        $normalized = $this->normalize($name);
        if ($normalized['name'] === '') {
            return null;
        }

        return DB::transaction(function () use ($normalized, $tribe): Place {
            if ($existing = $this->findForTribe($normalized['name_key'], $tribe)) {
                return $existing;
            }

            try {
                return Place::create([
                    ...$normalized,
                    'tribe' => $tribe,
                    'scope_key' => self::scopeKey($tribe),
                    'is_provisional' => true,
                ]);
            } catch (QueryException $exception) {
                $existing = $this->findForTribe($normalized['name_key'], $tribe);
                if (! $existing) {
                    throw $exception;
                }

                return $existing;
            }
        });
    }

    public function assertCompatible(?int $placeId, string $tribe): void
    {
        if ($placeId === null) {
            return;
        }

        $place = Place::find($placeId);
        if (! $place || ($place->tribe !== null && $place->tribe !== $tribe)) {
            throw ValidationException::withMessages(['place_id' => '這個地名不屬於所選部落']);
        }
    }

    public function update(Place $place, array $data): Place
    {
        $normalized = $this->normalize($data['name']);
        $tribe = $data['tribe'] ?? null;
        $scopeKey = self::scopeKey($tribe);

        return DB::transaction(function () use ($place, $data, $normalized, $tribe, $scopeKey): Place {
            $locked = Place::query()->lockForUpdate()->findOrFail($place->id);
            if ($tribe !== null && $locked->captureSessions()->where('tribe', '!=', $tribe)->exists()) {
                throw ValidationException::withMessages(['tribe' => '此地名被其他部落的情境使用']);
            }

            if (Place::where('scope_key', $scopeKey)->where('name_key', $normalized['name_key'])->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['name' => '已有同名地名']);
            }

            $locked->update([
                ...$normalized,
                'tribe' => $tribe,
                'scope_key' => $scopeKey,
                'tao_name' => $data['tao_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_provisional' => $data['is_provisional'] ?? $locked->is_provisional,
            ]);
            $sessionIds = $locked->captureSessions()->pluck('id');
            CaptureRecord::withTrashed()->whereIn('session_id', $sessionIds)->update(['location' => $locked->name]);

            return $locked->refresh();
        });
    }

    public function confirm(Place $place): Place
    {
        $place->update(['is_provisional' => false]);

        return $place->refresh();
    }

    public function merge(Place $source, Place $target): Place
    {
        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_place_id' => '不能併入同一個地名']);
        }

        return DB::transaction(function () use ($source, $target): Place {
            $ids = [$source->id, $target->id];
            sort($ids);
            $locked = Place::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedSource = $locked->get($source->id);
            $lockedTarget = $locked->get($target->id);
            if (! $lockedSource || ! $lockedTarget) {
                throw ValidationException::withMessages(['target_place_id' => '地名已不存在']);
            }

            $sessions = CaptureSession::query()->where('place_id', $lockedSource->id)->lockForUpdate()->get();
            if ($lockedTarget->tribe !== null && $sessions->contains(fn (CaptureSession $session) => $session->tribe !== $lockedTarget->tribe)) {
                throw ValidationException::withMessages(['target_place_id' => '此地名被其他部落的情境使用，不能併入']);
            }

            $sessionIds = $sessions->pluck('id');
            CaptureSession::whereIn('id', $sessionIds)->update(['place_id' => $lockedTarget->id]);
            CaptureRecord::withTrashed()->whereIn('session_id', $sessionIds)->update(['location' => $lockedTarget->name]);
            $lockedSource->delete();

            return $lockedTarget->refresh();
        });
    }

    public function delete(Place $place): void
    {
        if ($place->captureSessions()->exists()) {
            throw ValidationException::withMessages(['place' => '此地名仍被情境使用，不能刪除']);
        }

        $place->delete();
    }
}
