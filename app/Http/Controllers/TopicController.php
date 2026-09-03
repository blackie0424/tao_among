<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\TopicItem;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends BaseController
{
    /**
     * 首頁 API - 返回已發布的知識分類
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
     * 知識分類清單頁 - 顯示某分類下的已發布項目
     */
    public function index(string $slug): Response
    {
        $category = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $items = TopicItem::where('topic_id', $category->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Topic/Index', [
            'category' => $category,
            'items' => $items,
        ]);
    }

    /**
     * 知識項目詳細頁
     */
    public function show(string $slug, int $itemId): Response
    {
        $category = Topic::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $item = TopicItem::where('id', $itemId)
            ->where('topic_id', $category->id)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('Topic/Show', [
            'category' => $category,
            'item' => $item,
        ]);
    }
}
