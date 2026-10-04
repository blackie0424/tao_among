<?php

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TopicItemMediaManager
{
    public function __construct(private readonly StorageServiceInterface $storageService) {}

    /**
     * @param array<int, array{id?: int, type: string, source: string}> $mediaItems
     */
    public function sync(TopicItem $topicItem, array $mediaItems): void
    {
        $pathsToDelete = DB::transaction(function () use ($topicItem, $mediaItems): array {
            $existing = $topicItem->media()->get()->keyBy('id');
            $pathsToDelete = [];

            foreach (array_values($mediaItems) as $sortOrder => $mediaData) {
                $media = isset($mediaData['id'])
                    ? $existing->pull($mediaData['id'])
                    : null;

                if ($media) {
                    if ($media->type === TopicItemMedia::TYPE_IMAGE
                        && ($mediaData['type'] !== TopicItemMedia::TYPE_IMAGE || $media->source !== $mediaData['source'])) {
                        $pathsToDelete[] = $media->source;
                    }

                    $media->update([
                        'type' => $mediaData['type'],
                        'source' => $mediaData['source'],
                        'sort_order' => $sortOrder,
                    ]);
                } else {
                    $topicItem->media()->create([
                        'type' => $mediaData['type'],
                        'source' => $mediaData['source'],
                        'sort_order' => $sortOrder,
                    ]);
                }
            }

            foreach ($existing as $media) {
                if ($media->type === TopicItemMedia::TYPE_IMAGE) {
                    $pathsToDelete[] = $media->source;
                }
                $media->delete();
            }

            return array_values(array_unique($pathsToDelete));
        });

        $this->deleteImagesAfterCommit($pathsToDelete);
        $topicItem->unsetRelation('media');
    }

    public function deleteForItem(TopicItem $topicItem): void
    {
        DB::transaction(function () use ($topicItem): void {
            $imagePaths = $topicItem->media()
                ->where('type', TopicItemMedia::TYPE_IMAGE)
                ->pluck('source')
                ->all();

            $topicItem->delete();
            $this->deleteImagesAfterCommit($imagePaths);
        });
    }

    /** @param array<int, string> $imagePaths */
    private function deleteImagesAfterCommit(array $imagePaths): void
    {
        if ($imagePaths === []) {
            return;
        }

        DB::afterCommit(function () use ($imagePaths): void {
            $referencedPaths = TopicItemMedia::query()
                ->where('type', TopicItemMedia::TYPE_IMAGE)
                ->whereIn('source', $imagePaths)
                ->pluck('source')
                ->all();

            $this->deleteImages(array_values(array_diff($imagePaths, $referencedPaths)));
        });
    }

    /** @param array<int, string> $imagePaths */
    private function deleteImages(array $imagePaths): void
    {
        foreach ($imagePaths as $imagePath) {
            try {
                $this->storageService->delete($imagePath);
            } catch (\Throwable $exception) {
                Log::error('Failed to delete topic item image: '.$exception->getMessage(), [
                    'image_path' => $imagePath,
                ]);
            }
        }
    }
}
