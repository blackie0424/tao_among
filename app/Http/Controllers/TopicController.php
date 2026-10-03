<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicItem;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends BaseController
{
    public function getPublishedCategories()
    {
        $categories = Topic::where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($categories);
    }

    public function index(string $slug): Response
    {
        $topic = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $items = TopicItem::with('media')
            ->where('topic_id', $topic->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Topic/Index', [
            'topic' => $topic,
            'items' => $items,
        ]);
    }

    public function show(string $slug, int $itemId): Response
    {
        $topic = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $item = TopicItem::with('media')
            ->where('id', $itemId)
            ->where('topic_id', $topic->id)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('Topic/Show', [
            'topic' => $topic,
            'item' => $item,
        ]);
    }
}
