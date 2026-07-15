<?php

namespace App\Http\Controllers\Api;

use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class ImageRotateController extends Controller
{
    public function __construct(private StorageServiceInterface $storage) {}

    /**
     * POST /prefix/api/fish/{id}/image/rotate
     * 旋轉魚類首圖並覆蓋 S3；若 has_webp = true 則同步重新產生 WebP
     */
    public function rotateFishImage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'image' => ['required', File::image()->max(10 * 1024)],
        ]);

        $fish = Fish::findOrFail($id);
        $file = $request->file('image');

        $imagePath = $this->storage->getImageFolder() . '/' . $fish->image;
        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        $this->storage->putContent($imagePath, $file->getContent(), $mimeType);

        if ($fish->has_webp) {
            $this->regenerateWebp($file->getContent(), $fish->image);
        }

        return response()->json(['message' => 'success']);
    }

    /**
     * POST /prefix/api/fish/{id}/capture-records/{recordId}/image/rotate
     * 旋轉捕獲紀錄圖片並覆蓋 S3
     */
    public function rotateCaptureRecordImage(Request $request, int $fishId, int $recordId): JsonResponse
    {
        $request->validate([
            'image' => ['required', File::image()->max(10 * 1024)],
        ]);

        $record = CaptureRecord::where('fish_id', $fishId)->findOrFail($recordId);
        $file = $request->file('image');

        $imagePath = $this->storage->getImageFolder() . '/' . $record->image_path;
        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        $this->storage->putContent($imagePath, $file->getContent(), $mimeType);

        return response()->json(['message' => 'success']);
    }

    private function regenerateWebp(string $content, string $filename): void
    {
        $gdImage = @imagecreatefromstring($content);
        if (!$gdImage) {
            return;
        }

        ob_start();
        imagewebp($gdImage, null, 90);
        $webpContent = ob_get_clean();
        imagedestroy($gdImage);

        if ($webpContent === false || $webpContent === '') {
            return;
        }

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $webpPath = $this->storage->getWebpFolder() . '/' . $baseName . '.webp';
        $this->storage->putContent($webpPath, $webpContent, 'image/webp');
    }
}
