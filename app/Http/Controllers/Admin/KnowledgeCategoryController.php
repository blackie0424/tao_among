<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\KnowledgeCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeCategoryController extends BaseController
{
    public function index(): Response
    {
        return Inertia::render('Admin/KnowledgeCategories/Index', [
            'categories' => KnowledgeCategory::orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function edit(KnowledgeCategory $knowledgeCategory): Response
    {
        return Inertia::render('Admin/KnowledgeCategories/Edit', [
            'category' => $knowledgeCategory,
        ]);
    }

    public function update(Request $request, KnowledgeCategory $knowledgeCategory)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image_path' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $updateData = [
            'title' => $data['title'],
            'sort_order' => $data['sort_order'] ?? $knowledgeCategory->sort_order,
            'is_published' => $data['is_published'] ?? false,
        ];

        if (!empty($data['image_path'])) {
            $updateData['image_path'] = $data['image_path'];
        }

        $knowledgeCategory->update($updateData);

        return redirect('/admin/knowledge-categories')->with('success', '知識分類已成功更新');
    }

    public function togglePublished(KnowledgeCategory $knowledgeCategory)
    {
        $knowledgeCategory->update(['is_published' => !$knowledgeCategory->is_published]);

        return redirect('/admin/knowledge-categories')->with('success', '發布狀態已更新');
    }

    public function moveUp(KnowledgeCategory $knowledgeCategory)
    {
        // 找到上一筆 (sort_order 較小的)
        $previous = KnowledgeCategory::where('sort_order', '<', $knowledgeCategory->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            // 交換 sort_order
            $temp = $knowledgeCategory->sort_order;
            $knowledgeCategory->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $temp]);
        }

        return redirect('/admin/knowledge-categories')->with('success', '排序已更新');
    }

    public function moveDown(KnowledgeCategory $knowledgeCategory)
    {
        // 找到下一筆 (sort_order 較大的)
        $next = KnowledgeCategory::where('sort_order', '>', $knowledgeCategory->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            // 交換 sort_order
            $temp = $knowledgeCategory->sort_order;
            $knowledgeCategory->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
        }

        return redirect('/admin/knowledge-categories')->with('success', '排序已更新');
    }
}
