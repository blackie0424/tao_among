<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use App\Rules\YouTubeUrl;
use App\Services\TopicItemMediaManager;
use App\Support\YouTubeVideoId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TopicItemController extends BaseController
{
    public function __construct(private readonly TopicItemMediaManager $mediaManager) {}

    public function index(Request $request): Response
    {
        $topicId = $request->query('topic_id');
        $query = TopicItem::with(['topic', 'media'])->orderBy('sort_order')->orderBy('id');

        if ($topicId) {
            $query->where('topic_id', $topicId);
        }

        return Inertia::render('Admin/TopicItems/Index', [
            'items' => $query->paginate(20)->withQueryString(),
            'topics' => Topic::orderBy('sort_order')->get(),
            'selectedTopicId' => $topicId ? (int) $topicId : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $topicId = $request->query('topic_id');

        return Inertia::render('Admin/TopicItems/Create', [
            'topics' => Topic::orderBy('sort_order')->get(),
            'selectedTopicId' => $topicId ? (int) $topicId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $media] = $this->validatedPayload($request);

        $item = DB::transaction(function () use ($data, $media): TopicItem {
            if (! isset($data['sort_order'])) {
                $maxSortOrder = TopicItem::where('topic_id', $data['topic_id'])->max('sort_order');
                $data['sort_order'] = ($maxSortOrder ?? -1) + 1;
            }

            $item = TopicItem::create([
                'topic_id' => $data['topic_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'],
                'is_published' => $data['is_published'] ?? false,
            ]);
            $this->mediaManager->sync($item, $media);

            return $item;
        });

        return redirect('/admin/topic-items?topic_id='.$item->topic_id)
            ->with('success', '知識項目已成功建立');
    }

    public function edit(TopicItem $topicItem): Response
    {
        return Inertia::render('Admin/TopicItems/Edit', [
            'item' => $topicItem->load(['topic', 'media']),
            'topics' => Topic::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, TopicItem $topicItem): RedirectResponse
    {
        [$data, $media] = $this->validatedPayload($request, $topicItem);

        DB::transaction(function () use ($topicItem, $data, $media): void {
            $topicItem->update([
                'topic_id' => $data['topic_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? $topicItem->sort_order,
                'is_published' => $data['is_published'] ?? false,
            ]);
            $this->mediaManager->sync($topicItem, $media);
        });

        return redirect('/admin/topic-items?topic_id='.$data['topic_id'])
            ->with('success', '知識項目已成功更新');
    }

    public function destroy(TopicItem $topicItem): RedirectResponse
    {
        $topicId = $topicItem->topic_id;
        $this->mediaManager->deleteForItem($topicItem);

        return redirect('/admin/topic-items?topic_id='.$topicId)
            ->with('success', '知識項目已成功刪除');
    }

    public function togglePublished(TopicItem $topicItem): RedirectResponse
    {
        $topicItem->update(['is_published' => ! $topicItem->is_published]);

        return back()->with('success', '發布狀態已更新');
    }

    public function moveUp(TopicItem $topicItem): RedirectResponse
    {
        $previous = TopicItem::where('topic_id', $topicItem->topic_id)
            ->where('sort_order', '<', $topicItem->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            $currentSortOrder = $topicItem->sort_order;
            $topicItem->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $currentSortOrder]);
        }

        return back()->with('success', '排序已更新');
    }

    public function moveDown(TopicItem $topicItem): RedirectResponse
    {
        $next = TopicItem::where('topic_id', $topicItem->topic_id)
            ->where('sort_order', '>', $topicItem->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            $currentSortOrder = $topicItem->sort_order;
            $topicItem->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $currentSortOrder]);
        }

        return back()->with('success', '排序已更新');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{id?: int, type: string, source: string}>}
     */
    private function validatedPayload(Request $request, ?TopicItem $topicItem = null): array
    {
        $data = $request->validate([
            'topic_id' => ['required', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'image_path' => ['nullable', 'string'],
            'remove_image' => ['nullable', 'boolean'],
            'media' => ['nullable', 'array'],
            'media.*.id' => ['nullable', 'integer'],
            'media.*.type' => ['required', Rule::in(TopicItemMedia::TYPES)],
            'media.*.source' => ['required', 'string', 'max:2048'],
        ]);

        $mediaItems = $request->exists('media')
            ? array_values($data['media'] ?? [])
            : $this->legacyMediaPayload($request, $topicItem);
        unset($data['media'], $data['image_path'], $data['remove_image']);

        $existingIds = $topicItem?->media()->pluck('id')->all() ?? [];
        $submittedIds = [];
        $errors = [];

        foreach ($mediaItems as $index => &$media) {
            if (isset($media['id'])) {
                if (! in_array($media['id'], $existingIds, true) || in_array($media['id'], $submittedIds, true)) {
                    $errors["media.{$index}.id"] = '媒體資料不屬於此項目或重複提交。';
                }
                $submittedIds[] = $media['id'];
            }

            if ($media['type'] === TopicItemMedia::TYPE_YOUTUBE) {
                (new YouTubeUrl)->validate(
                    "media.{$index}.source",
                    $media['source'],
                    function (string $message) use (&$errors, $index): void {
                        $errors["media.{$index}.source"] = $message;
                    },
                );

                $videoId = YouTubeVideoId::fromUrl($media['source']);
                if ($videoId !== null) {
                    $media['source'] = $videoId;
                }
            }
        }
        unset($media);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$data, $mediaItems];
    }

    /**
     * Keeps the previous single-image form operational until the multi-media UI is deployed.
     *
     * @return array<int, array{id?: int, type: string, source: string}>
     */
    private function legacyMediaPayload(Request $request, ?TopicItem $topicItem): array
    {
        $mediaItems = $topicItem?->media()->get()->map(fn (TopicItemMedia $media): array => [
            'id' => $media->id,
            'type' => $media->type,
            'source' => $media->type === TopicItemMedia::TYPE_YOUTUBE
                ? $media->youtube_url
                : $media->source,
        ])->values()->all() ?? [];

        $newImagePath = trim((string) $request->input('image_path', ''));
        $imageIndex = collect($mediaItems)->search(
            fn (array $media): bool => $media['type'] === TopicItemMedia::TYPE_IMAGE,
        );

        if ($newImagePath !== '') {
            $newImage = ['type' => TopicItemMedia::TYPE_IMAGE, 'source' => $newImagePath];
            if ($imageIndex === false) {
                array_unshift($mediaItems, $newImage);
            } else {
                $mediaItems[$imageIndex] = array_merge($mediaItems[$imageIndex], $newImage);
            }
        } elseif ($request->boolean('remove_image') && $imageIndex !== false) {
            unset($mediaItems[$imageIndex]);
            $mediaItems = array_values($mediaItems);
        }

        return $mediaItems;
    }
}
