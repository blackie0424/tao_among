<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends BaseController
{
    public function index(): Response
    {
        return Inertia::render('Admin/Topics/Index', [
            'topics' => Topic::orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function edit(Topic $topic): Response
    {
        return Inertia::render('Admin/Topics/Edit', [
            'topic' => $topic,
        ]);
    }

    public function update(Request $request, Topic $topic)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image_path' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $updateData = [
            'title' => $data['title'],
            'sort_order' => $data['sort_order'] ?? $topic->sort_order,
            'is_published' => $data['is_published'] ?? false,
        ];

        if (!empty($data['image_path'])) {
            $updateData['image_path'] = $data['image_path'];
        }

        $topic->update($updateData);

        return redirect('/admin/topics')->with('success', '知識分類已成功更新');
    }

    public function togglePublished(Topic $topic)
    {
        $topic->update(['is_published' => !$topic->is_published]);

        return redirect('/admin/topics')->with('success', '發布狀態已更新');
    }

    public function moveUp(Topic $topic)
    {
        // 找到上一筆 (sort_order 較小的)
        $previous = Topic::where('sort_order', '<', $topic->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            // 交換 sort_order
            $temp = $topic->sort_order;
            $topic->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $temp]);
        }

        return redirect('/admin/topics')->with('success', '排序已更新');
    }

    public function moveDown(Topic $topic)
    {
        // 找到下一筆 (sort_order 較大的)
        $next = Topic::where('sort_order', '>', $topic->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            // 交換 sort_order
            $temp = $topic->sort_order;
            $topic->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
        }

        return redirect('/admin/topics')->with('success', '排序已更新');
    }
}
