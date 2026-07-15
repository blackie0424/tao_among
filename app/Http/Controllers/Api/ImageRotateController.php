<?php

namespace App\Http\Controllers\Api;

use App\Contracts\StorageServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'degrees' => ['required', 'integer', 'in:90,180,270'],
        ]);

        $degrees = (int) $request->input('degrees');
        $fish = Fish::findOrFail($id);

        $imagePath = $this->storage->getImageFolder() . '/' . $fish->image;
        $content = $this->storage->getContent($imagePath);
        $mimeType = $this->detectMimeType($fish->image);

        $rotatedContent = $this->rotateImageContent($content, $degrees, $mimeType);

        $this->storage->putContent($imagePath, $rotatedContent, $mimeType);

        if ($fish->has_webp) {
            $this->regenerateWebp($rotatedContent, $fish->image);
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
            'degrees' => ['required', 'integer', 'in:90,180,270'],
        ]);

        $degrees = (int) $request->input('degrees');
        $record = CaptureRecord::where('fish_id', $fishId)->findOrFail($recordId);

        $imagePath = $this->storage->getImageFolder() . '/' . $record->image_path;
        $content = $this->storage->getContent($imagePath);
        $mimeType = $this->detectMimeType($record->image_path);

        $rotatedContent = $this->rotateImageContent($content, $degrees, $mimeType);

        $this->storage->putContent($imagePath, $rotatedContent, $mimeType);

        return response()->json(['message' => 'success']);
    }

    private function rotateImageContent(string $content, int $degrees, string $mimeType): string
    {
        $gdImage = @imagecreatefromstring($content);
        if ($gdImage === false) {
            throw new \RuntimeException('無法讀取圖片內容');
        }

        // imagerotate 角度為逆時針，360 - $degrees 轉換為順時針
        $rotated = imagerotate($gdImage, 360 - $degrees, 0);
        imagedestroy($gdImage);

        if ($rotated === false) {
            throw new \RuntimeException('圖片旋轉失敗');
        }

        ob_start();
        match ($mimeType) {
            'image/png' => imagepng($rotated),
            'image/webp' => imagewebp($rotated),
            default => imagejpeg($rotated, null, 100),
        };
        $result = ob_get_clean();
        imagedestroy($rotated);

        if ($result === false || $result === '') {
            throw new \RuntimeException('圖片輸出失敗');
        }

        return $result;
    }

    private function detectMimeType(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
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
