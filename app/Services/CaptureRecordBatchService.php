<?php

namespace App\Services;

use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureRecordBatchService
{
    public function __construct(
        private readonly ?CaptureRecordFieldValidator $captureRecordFieldValidator = null,
        private readonly ?CaptureSessionService $captureSessionService = null,
    ) {}

    /**
     * Legacy creation path retained for LINE.
     *
     * @param  string[]  $filenames
     * @param  array{tribe:string,location:string,capture_method:string,capture_date:string,notes?:?string}  $sharedData
     * @return array<int, CaptureRecord>
     */
    public function createForFish(Fish $fish, array $filenames, array $sharedData): array
    {
        if (empty($filenames)) {
            throw ValidationException::withMessages(['image_filename' => '請上傳捕獲照片']);
        }

        $validated = $this->validateSharedData($sharedData);
        $records = [];

        foreach ($filenames as $filename) {
            $records[] = CaptureRecord::create([
                'fish_id' => $fish->id,
                'image_path' => $filename,
                'tribe' => $validated['tribe'],
                'location' => $validated['location'],
                'capture_method' => $validated['capture_method'],
                'capture_date' => $validated['capture_date'],
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return $records;
    }

    /**
     * @param  string[]  $filenames
     * @param  array{capture_date:string,tribe:string,capture_method:string,location?:?string}|null  $legacyCombo
     * @return array<int, CaptureRecord>
     */
    public function createForFishFromSession(
        Fish $fish,
        array $filenames,
        ?int $sessionId,
        ?array $legacyCombo,
        ?string $notes,
    ): array {
        if (empty($filenames)) {
            throw ValidationException::withMessages(['image_filename' => '請上傳捕獲照片']);
        }

        return DB::transaction(function () use ($fish, $filenames, $sessionId, $legacyCombo, $notes): array {
            $session = $this->captureSessionService()->resolveForCreate($sessionId, $legacyCombo);
            $attributes = $this->captureSessionService()->recordAttributes($session);

            return array_map(fn (string $filename) => CaptureRecord::create([
                'fish_id' => $fish->id,
                'session_id' => $session->id,
                'image_path' => $filename,
                ...$attributes,
                'notes' => $notes,
            ]), $filenames);
        });
    }

    /** @throws ValidationException */
    public function validateSharedData(array $sharedData): array
    {
        return $this->captureRecordFieldValidator()
            ->validateSharedData(array_merge(['image_filename' => 'line-batch-capture.jpg'], $sharedData));
    }

    private function captureRecordFieldValidator(): CaptureRecordFieldValidator
    {
        return $this->captureRecordFieldValidator ?? app(CaptureRecordFieldValidator::class);
    }

    private function captureSessionService(): CaptureSessionService
    {
        return $this->captureSessionService ?? app(CaptureSessionService::class);
    }
}
