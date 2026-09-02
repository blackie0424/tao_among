<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgeItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeItemController extends BaseController
{
    public function index(Request $request): Response
    {
        $categoryId = $request->query('category_id');
        
        $query = KnowledgeItem::with('category')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($categoryId) {
            $query->where('knowledge_category_id', $categoryId);
        }

        return Inertia::render('Admin/KnowledgeItems/Index', [
            'items' => $query->paginate(20)->withQueryString(),
            'categories' => KnowledgeCategory::orderBy('sort_order')->get(),
            'selectedCategoryId' => $categoryId ? (int)$categoryId : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $categoryId = $request->query('category_id');
        
        return Inertia::render('Admin/KnowledgeItems/Create', [
            'categories' => KnowledgeCategory::orderBy('sort_order')->get(),
            'selectedCategoryId' => $categoryId ? (int)$categoryId : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'knowledge_category_id' => 'required|exists:knowledge_categories,id',
            'title' => 'required|string|max:255',
            'image_path' => 'required|string',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        KnowledgeItem::create([
            'knowledge_category_id' => $data['knowledge_category_id'],
            'title' => $data['title'],
            'image_path' => $data['image_path'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? false,
        ]);

        return redirect('/admin/knowledge-items?category_id=' . $data['knowledge_category_id'])
            ->with('success', '知識項目已成功建立');
    }

    public function edit(KnowledgeItem $knowledgeItem): Response
    {
        return Inertia::render('Admin/KnowledgeItems/Edit', [
            'item' => $knowledgeItem->load('category'),
            'categories' => KnowledgeCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, KnowledgeItem $knowledgeItem)
    {
        $data = $request->validate([
            'knowledge_category_id' => 'required|exists:knowledge_categories,id',
            'title' => 'required|string|max:255',
            'image_path' => 'required|string',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $knowledgeItem->update([
            'knowledge_category_id' => $data['knowledge_category_id'],
            'title' => $data['title'],
            'image_path' => $data['image_path'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? $knowledgeItem->sort_order,
            'is_published' => $data['is_published'] ?? false,
        ]);

        return redirect('/admin/knowledge-items?category_id=' . $data['knowledge_category_id'])
            ->with('success', '知識項目已成功更新');
    }

    public function destroy(KnowledgeItem $knowledgeItem)
    {
        $categoryId = $knowledgeItem->knowledge_category_id;
        $knowledgeItem->delete();

        return redirect('/admin/knowledge-items?category_id=' . $categoryId)
            ->with('success', '知識項目已成功刪除');
    }

    public function togglePublished(KnowledgeItem $knowledgeItem)
    {
        $knowledgeItem->update(['is_published' => !$knowledgeItem->is_published]);

        return back()->with('success', '發布狀態已更新');
    }

    public function moveUp(KnowledgeItem $knowledgeItem)
    {
        // 找到同分類中上一筆 (sort_order 較小的)
        $previous = KnowledgeItem::where('knowledge_category_id', $knowledgeItem->knowledge_category_id)
            ->where('sort_order', '<', $knowledgeItem->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            // 交換 sort_order
            $temp = $knowledgeItem->sort_order;
            $knowledgeItem->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $temp]);
        }

        return back()->with('success', '排序已更新');
    }

    public function moveDown(KnowledgeItem $knowledgeItem)
    {
        // 找到同分類中下一筆 (sort_order 較大的)
        $next = KnowledgeItem::where('knowledge_category_id', $knowledgeItem->knowledge_category_id)
            ->where('sort_order', '>', $knowledgeItem->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            // 交換 sort_order
            $temp = $knowledgeItem->sort_order;
            $knowledgeItem->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
        }

        return back()->with('success', '排序已更新');
    }
}
