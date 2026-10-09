<?php

namespace App\Services;

use App\Models\CaptureRecord;
use Illuminate\Support\Collection;
use LogicException;

class CaptureRecordPresenter
{
    /** @return array<string, mixed> */
    public function presentWithSessionNotes(CaptureRecord $record): array
    {
        if (! $record->relationLoaded('captureSession')) {
            throw new LogicException('Capture record session notes require an eager-loaded captureSession relation.');
        }

        $payload = $record->toArray();
        unset($payload['capture_session']);
        $payload['session_notes'] = $record->captureSession?->notes;

        return $payload;
    }

    /**
     * @param  Collection<int, CaptureRecord>  $records
     * @return array<int, array<string, mixed>>
     */
    public function presentManyWithSessionNotes(Collection $records): array
    {
        return $records
            ->map(fn (CaptureRecord $record) => $this->presentWithSessionNotes($record))
            ->values()
            ->all();
    }
}
