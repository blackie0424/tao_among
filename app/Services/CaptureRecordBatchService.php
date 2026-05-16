<?php

namespace App\Services;

use App\Http\Requests\CaptureRecordRequest;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CaptureRecordBatchService
{
    /**
     * @param string[] $filenames
     * @param array{tribe:string,location:string,capture_method:string,capture_date:string,notes?:?string} $sharedData
     * @return array<int, CaptureRecord>
     *
     * @throws ValidationException
     */
    public function createForFish(Fish $fish, array $filenames, array $sharedData): array
    {
        if (empty($filenames)) {
            throw ValidationException::withMessages([
                'image_filename' => '請上傳捕獲照片',
            ]);
        }

        $validated = $this->validateSharedData($sharedData);
        $records = [];

        foreach ($filenames as $filename) {
            $records[] = CaptureRecord::create([
                'fish_id'        => $fish->id,
                'image_path'     => $filename,
                'tribe'          => $validated['tribe'],
                'location'       => $validated['location'],
                'capture_method' => $validated['capture_method'],
                'capture_date'   => $validated['capture_date'],
                'notes'          => $validated['notes'] ?? null,
            ]);
        }

        return $records;
    }

    /**
     * @param array{tribe?:string,location?:string,capture_method?:string,capture_date?:string,notes?:?string} $sharedData
     * @return array{image_filename:string,tribe:string,location:string,capture_method:string,capture_date:string,notes?:?string}
     *
     * @throws ValidationException
     */
    public function validateSharedData(array $sharedData): array
    {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');

        return Validator::make(
            array_merge(['image_filename' => 'line-batch-capture.jpg'], $sharedData),
            $request->rules(),
            $request->messages(),
            $request->attributes()
        )->validate();
    }
}
