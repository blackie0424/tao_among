<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Topic;
use App\Models\TopicItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicItemController extends BaseController
{
    public function index(Request $request): Response
    {
        $topicId = $request->query('topic_id');
        
        $query = TopicItem::with('topic')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($topicId) {
            $query->where('topic_id', $topicId);
        }

        return Inertia::render('Admin/TopicItems/Index', [
            'items' => $query->paginate(20)->withQueryString(),
            'topics' => Topic::orderBy('sort_order')->get(),
            'selectedTopicId' => $topicId ? (int)$topicId : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $topicId = $request->query('topic_id');
        
        return Inertia::render('Admin/TopicItems/Create', [
            'topics' => Topic::orderBy('sort_order')->get(),
            'selectedTopicId' => $topicId ? (int)$topicId : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'topic_id' => 'required|exists:topics,id',
            'title' => 'required|string|max:255',
            'image_path' => 'required|string',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        // 如果沒有提供 sort_order,自動設為該分類目前最大值 + 1
        if (!isset($data['sort_order'])) {
            $maxSortOrder = TopicItem::where('topic_id', $data['topic_id'])
                ->max('sort_order');
            $data['sort_order'] = ($maxSortOrder ?? -1) + 1;
        }

        TopicItem::create([
            'topic_id' => $data['topic_id'],
            'title' => $data['title'],
            'image_path' => $data['image_path'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_published' => $data['is_published'] ?? false,
        ]);

        return redirect('/admin/topic-items?topic_id=' . $data['topic_id'])
            ->with('success', '知識項目已成功建立');
    }

    public function edit(TopicItem $topicItem): Response
    {
        return Inertia::render('Admin/TopicItems/Edit', [
            'item' => $topicItem->load('topic'),
            'topics' => Topic::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, TopicItem $topicItem)
    {
        $data = $request->validate([
            'topic_id' => 'required|exists:topics,id',
            'title' => 'required|string|max:255',
            'image_path' => 'nullable|string',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $updateData = [
            'topic_id' => $data['topic_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? $topicItem->sort_order,
            'is_published' => $data['is_published'] ?? false,
        ];

        if (isset($data['image_path'])) {
            $updateData['image_path'] = $data['image_path'];
        }

        $topicItem->update($updateData);

        return redirect('/admin/topic-items?topic_id=' . $data['topic_id'])
            ->with('success', '知識項目已成功更新');
    }

    public function destroy(TopicItem $topicItem)
    {
        $topicId = $topicItem->topic_id;
        $topicItem->delete();

        return redirect('/admin/topic-items?topic_id=' . $topicId)
            ->with('success', '知識項目已成功刪除');
    }

    public function togglePublished(TopicItem $topicItem)
    {
        $topicItem->update(['is_published' => !$topicItem->is_published]);

        return back()->with('success', '發布狀態已更新');
    }

    public function moveUp(TopicItem $topicItem)
    {
        // 找到同分類中上一筆 (sort_order 較小的)
        $previous = TopicItem::where('topic_id', $topicItem->topic_id)
            ->where('sort_order', '<', $topicItem->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            // 交換 sort_order
            $temp = $topicItem->sort_order;
            $topicItem->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $temp]);
        }

        return back()->with('success', '排序已更新');
    }

    public function moveDown(TopicItem $topicItem)
    {
        // 找到同分類中下一筆 (sort_order 較大的)
        $next = TopicItem::where('topic_id', $topicItem->topic_id)
            ->where('sort_order', '>', $topicItem->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            // 交換 sort_order
            $temp = $topicItem->sort_order;
            $topicItem->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
        }

        return back()->with('success', '排序已更新');
    }
}
