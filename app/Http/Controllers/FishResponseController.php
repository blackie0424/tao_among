<?php

namespace App\Http\Controllers;

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FishResponseController extends Controller
{
    private const RECORDING_MISSING_MESSAGE = '錄音檔不存在或太大，請重錄';

    private const SECONDS_WITHOUT_RECORDING_MESSAGE = '沒有錄音時不能帶錄音秒數';

    private const RECORDING_SECONDS_MESSAGE = '錄音秒數不正確，請重錄';

    private const LOG_PREFIX = '[response-recording]';

    public function store(Request $request, int $id, StorageServiceInterface $storage): RedirectResponse
    {
        $fish = Fish::findOrFail($id);
        $input = $this->normalizedInput($request);

        $validator = Validator::make($input, [
            'tribe' => ['required', Rule::in(config('fish_options.tribes'))],
            'name' => ['nullable', 'string', 'max:'.FishTribeResponse::NAME_MAX_LENGTH],
            'food_category' => ['nullable', Rule::in(FishTribeResponse::selectableFoodCategories())],
            'recording' => ['nullable', 'string', 'regex:'.FishTribeResponse::recordingFilenamePattern()],
            'recording_seconds' => ['nullable', 'integer', 'between:1,'.FishTribeResponse::RECORDING_MAX_SECONDS, 'required_with:recording'],
        ], [
            'name.max' => '叫法最多 '.FishTribeResponse::NAME_MAX_LENGTH.' 字',
            'recording.regex' => self::RECORDING_MISSING_MESSAGE,
            'recording_seconds.integer' => self::RECORDING_SECONDS_MESSAGE,
            'recording_seconds.between' => self::RECORDING_SECONDS_MESSAGE,
            'recording_seconds.required_with' => self::RECORDING_SECONDS_MESSAGE,
        ]);
        // 沒有錄音卻帶了秒數：兩者對不上，拒絕
        $validator->after(function ($validator) use ($input) {
            if ($input['recording'] === null && $input['recording_seconds'] !== null) {
                $validator->errors()->add('recording_seconds', self::SECONDS_WITHOUT_RECORDING_MESSAGE);
            }
        });
        $data = $validator->validate();

        $userId = $request->user()->id;
        $keys = ['user_id' => $userId, 'fish_id' => $fish->id, 'tribe' => $data['tribe']];
        $stored = $data['recording'] === null ? null : $this->copyPendingRecording($storage, $userId, $data['recording']);

        try {
            $replaced = FishTribeResponse::submit($keys, $data['name'], $data['food_category'], $stored, $data['recording_seconds'] === null ? null : (int) $data['recording_seconds']);
        } catch (Throwable $e) {
            if ($stored !== null) {
                $this->deleteRecordingFile($storage, $stored);
            }
            throw $e;
        }

        if ($data['recording'] !== null) {
            if ($replaced !== null) {
                $this->deleteRecordingFile($storage, $replaced);
            }
            $this->deleteStorageFile($storage, FishTribeResponse::pendingRecordingPath($userId, $data['recording']), $data['recording']);
        }

        return back();
    }

    /** 作者刪除自己在某部落的錄音。 */
    public function destroyRecording(Request $request, int $id, StorageServiceInterface $storage): RedirectResponse
    {
        $fish = Fish::findOrFail($id);
        $data = $request->validate(['tribe' => ['required', Rule::in(config('fish_options.tribes'))]]);

        $deleted = FishTribeResponse::removeRecording(
            ['user_id' => $request->user()->id, 'fish_id' => $fish->id, 'tribe' => $data['tribe']],
            fn (string $filename) => $storage->delete(FishTribeResponse::recordingPath($filename)),
        );

        return $deleted ? back() : back()->withErrors(['recording' => '刪除失敗，請重試']);
    }

    /**
     * 取得錄音的上傳網址：只簽名，不寫任何資料表，也不碰正式的魚聲音資料。
     * 檔名由伺服器產生，放在登入使用者專屬的暫存資料夾。
     */
    public function recordingUploadUrl(Request $request, int $id, StorageServiceInterface $storage): JsonResponse
    {
        Fish::findOrFail($id);
        $ext = $request->validate([
            'ext' => ['nullable', Rule::in(FishTribeResponse::RECORDING_EXTENSIONS)],
        ])['ext'] ?? FishTribeResponse::RECORDING_EXTENSIONS[0];

        $filename = Str::uuid()->toString().'.'.$ext;
        $url = $storage->createSignedUploadUrl(
            FishTribeResponse::pendingRecordingPath($request->user()->id, $filename),
            FishTribeResponse::RECORDING_UPLOAD_TTL_SECONDS,
        );
        if ($url === null) {
            return response()->json(['message' => '無法取得錄音上傳網址，請稍後再試'], 500);
        }

        return response()->json(['url' => $url, 'filename' => $filename]);
    }

    /**
     * 驗證使用者自己暫存資料夾裡的錄音（存在、不超過大小上限），再複製到正式資料夾；暫存檔先留著。
     *
     * @return string 正式資料夾裡的新檔名（每次複製都用新的 UUID，不同回答不會共用同一個檔）
     */
    private function copyPendingRecording(StorageServiceInterface $storage, int $userId, string $pendingName): string
    {
        $pendingPath = FishTribeResponse::pendingRecordingPath($userId, $pendingName);
        $size = $storage->fileSize($pendingPath);
        if ($size === null) {
            throw ValidationException::withMessages(['recording' => self::RECORDING_MISSING_MESSAGE]);
        }
        if ($size > FishTribeResponse::RECORDING_MAX_BYTES) {
            // 太大的暫存檔直接刪掉；刪不掉只記日誌，仍然拒絕這次送出
            $this->deleteStorageFile($storage, $pendingPath, $pendingName);
            throw ValidationException::withMessages(['recording' => self::RECORDING_MISSING_MESSAGE]);
        }

        $stored = Str::uuid()->toString().'.'.pathinfo($pendingName, PATHINFO_EXTENSION);
        if (! $storage->copyObject($pendingPath, FishTribeResponse::recordingPath($stored))) {
            throw ValidationException::withMessages(['recording' => '錄音儲存失敗，請重試']);
        }

        return $stored;
    }

    private function deleteRecordingFile(StorageServiceInterface $storage, string $filename): void
    {
        $this->deleteStorageFile($storage, FishTribeResponse::recordingPath($filename), $filename);
    }

    /** 盡力刪檔：失敗不影響回應，但留下記錄（檔案已無資料表參照）。 */
    private function deleteStorageFile(StorageServiceInterface $storage, string $path, string $filename): void
    {
        if (! $storage->delete($path)) {
            Log::warning(self::LOG_PREFIX.' 刪除檔案失敗', ['filename' => $filename, 'path' => $path]);
        }
    }

    /** 叫法去前後空白（含全形空白），空字串視為未填；飲食分類空字串視為未填。 */
    private function normalizedInput(Request $request): array
    {
        $name = $request->input('name');
        $name = is_string($name) ? preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $name) : $name;
        $category = $request->input('food_category');
        $recording = $request->input('recording');
        $recordingSeconds = $request->input('recording_seconds');

        return [
            'tribe' => $request->input('tribe'),
            'name' => $name === '' ? null : $name,
            'food_category' => $category === '' ? null : $category,
            'recording' => $recording === '' ? null : $recording,
            'recording_seconds' => $recordingSeconds === '' ? null : $recordingSeconds,
        ];
    }
}
