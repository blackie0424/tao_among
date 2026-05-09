<?php

namespace App\Services\GoogleDocs;

use App\Contracts\StorageServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Support\Collection;

class FishCatalogLayoutBuilder
{
    public function __construct(
        private StorageServiceInterface $storage
    ) {
    }

    public function build(Fish $fish): array
    {
        $frontRecord = $this->resolveFrontCaptureRecord($fish);
        $frontImageFilename = $frontRecord?->image_path ?: $this->normalizeImageFilename($fish->image);

        return [
            'front' => [
                'heading' => '基本資料',
                'image_filename' => $frontImageFilename,
                'name_line' => "名稱：{$fish->name}",
                'qr_code_url' => $this->buildQrCodeUrl($fish),
                'capture_method_line' => '捕獲方式：' . ($frontRecord?->capture_method ?: $this->resolveLatestCaptureMethod($fish)),
                'knowledge_heading' => '地方知識',
                'knowledge_table' => $this->buildKnowledgeTable($fish),
            ],
            'back' => [
                'image_filename' => $this->resolveBackImageFilename($fish, $frontImageFilename),
                'lines' => [
                    '生態：',
                    '分布：',
                    '傳統價值：',
                    '魚餌：',
                ],
            ],
        ];
    }

    private function resolveFrontCaptureRecord(Fish $fish): ?CaptureRecord
    {
        if (!$fish->display_capture_record_id) {
            return null;
        }

        if ($fish->relationLoaded('displayCaptureRecord')) {
            $record = $fish->displayCaptureRecord;

            return $record && !$record->trashed() ? $record : null;
        }

        if ($fish->relationLoaded('captureRecords')) {
            return $fish->captureRecords
                ->firstWhere('id', $fish->display_capture_record_id);
        }

        $record = $fish->displayCaptureRecord()->first();

        return $record && !$record->trashed() ? $record : null;
    }

    private function resolveBackImageFilename(Fish $fish, ?string $frontImageFilename): ?string
    {
        $captureRecords = $this->getCaptureRecords($fish);

        return $captureRecords
            ->pluck('image_path')
            ->map(fn (?string $filename) => $this->normalizeImageFilename($filename))
            ->first(fn (?string $filename) => $filename !== null && $filename !== $frontImageFilename);
    }

    private function resolveLatestCaptureMethod(Fish $fish): string
    {
        $captureMethod = $this->getCaptureRecords($fish)
            ->pluck('capture_method')
            ->first(fn (?string $method) => filled($method));

        return $captureMethod ?: '';
    }

    private function buildKnowledgeTable(Fish $fish): array
    {
        $classifications = $this->getTribalClassifications($fish)->keyBy('tribe');

        return [
            ['部落', '食用類別', '處理方式'],
            [
                'Imowrod',
                $classifications->get('imowrod')?->food_category ?: '尚未紀錄',
                $classifications->get('imowrod')?->processing_method ?: '尚未紀錄',
            ],
            [
                'Iraraley',
                $classifications->get('iraraley')?->food_category ?: '尚未紀錄',
                $classifications->get('iraraley')?->processing_method ?: '尚未紀錄',
            ],
        ];
    }

    private function buildQrCodeUrl(Fish $fish): ?string
    {
        if (blank($fish->audio_filename)) {
            return null;
        }

        $audioUrl = $this->storage->getUrl('audios', $fish->audio_filename, null);

        return 'https://quickchart.io/qr?text=' . rawurlencode($audioUrl) . '&size=220';
    }

    private function getCaptureRecords(Fish $fish): Collection
    {
        if ($fish->relationLoaded('captureRecords')) {
            return $fish->captureRecords;
        }

        return $fish->captureRecords()->orderByDesc('capture_date')->orderByDesc('id')->get();
    }

    private function getTribalClassifications(Fish $fish): Collection
    {
        if ($fish->relationLoaded('tribalClassifications')) {
            return $fish->tribalClassifications;
        }

        return $fish->tribalClassifications()->whereIn('tribe', ['imowrod', 'iraraley'])->get();
    }

    private function normalizeImageFilename(?string $filename): ?string
    {
        if (blank($filename) || $filename === 'default.png') {
            return null;
        }

        return $filename;
    }
}
