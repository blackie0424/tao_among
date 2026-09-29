<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicItem;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends BaseController
{
    /**
     * 首頁 API - 返回已發布的主題分類
     */
    public function getPublishedCategories()
    {
        $categories = Topic::where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($categories);
    }

    /**
     * 主題分類清單頁 - 顯示某分類下的已發布項目
     */
    public function index(string $slug): Response
    {
        $topic = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $items = TopicItem::where('topic_id', $topic->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Topic/Index', [
            'topic' => $topic,
            'items' => $items,
        ]);
    }

    /**
     * 主題項目詳細頁
     */
    public function show(string $slug, int $itemId): Response
    {
        $topic = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $item = TopicItem::where('id', $itemId)
            ->where('topic_id', $topic->id)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('Topic/Show', [
            'topic' => $topic,
            'item' => $item,
        ]);
    }
}
