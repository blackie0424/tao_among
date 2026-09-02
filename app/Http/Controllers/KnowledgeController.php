<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeItem;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends BaseController
{
    /**
     * 首頁 API - 返回已發布的知識分類
     */
    public function getPublishedCategories()
    {
        $categories = KnowledgeCategory::where('is_published', true)
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
        $category = KnowledgeCategory::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $items = KnowledgeItem::where('knowledge_category_id', $category->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Knowledge/Index', [
            'category' => $category,
            'items' => $items,
        ]);
    }

    /**
     * 知識項目詳細頁
     */
    public function show(string $slug, int $itemId): Response
    {
        $category = KnowledgeCategory::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $item = KnowledgeItem::where('id', $itemId)
            ->where('knowledge_category_id', $category->id)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('Knowledge/Show', [
            'category' => $category,
            'item' => $item,
        ]);
    }
}
