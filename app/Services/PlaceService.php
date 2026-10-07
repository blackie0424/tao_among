<?php

namespace App\Services;

use App\Models\CaptureRecord;
use App\Models\Place;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceService
{
    /** @return array{name:string,name_key:string} */
    public function normalize(string $name): array
    {
        $display = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', $name));

        return ['name' => $display, 'name_key' => mb_strtolower($display)];
    }

    public function update(Place $place, array $data): Place
    {
        $normalized = $this->normalize($data['name']);

        try {
            return DB::transaction(function () use ($place, $data, $normalized): Place {
                $duplicate = Place::where('name_key', $normalized['name_key'])->whereKeyNot($place->id)->first();
                if ($duplicate) {
                    throw ValidationException::withMessages(['name' => '已有同名地名']);
                }

                $place->update([...$normalized, 'tao_name' => $data['tao_name'] ?? null, 'notes' => $data['notes'] ?? null]);
                $sessionIds = $place->captureSessions()->pluck('id');
                CaptureRecord::withTrashed()->whereIn('session_id', $sessionIds)->update(['location' => $place->name]);

                return $place->refresh();
            });
        } catch (QueryException $exception) {
            if (! Place::where('name_key', $normalized['name_key'])->whereKeyNot($place->id)->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages(['name' => '已有同名地名']);
        }
    }

    public function delete(Place $place): void
    {
        if ($place->captureSessions()->exists()) {
            throw ValidationException::withMessages(['place' => '此地名仍被情境使用，不能刪除']);
        }

        $place->delete();
    }
}
