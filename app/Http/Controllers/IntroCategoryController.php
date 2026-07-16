<?php

namespace App\Http\Controllers;

use App\Models\IntroCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntroCategoryController extends BaseController
{
    public function index(): Response
    {
        return Inertia::render('Admin/IntroCategories/Index', [
            'categories' => IntroCategory::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/IntroCategories/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        IntroCategory::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect('/admin/intro-categories')->with('success', '分類已成功建立');
    }

    public function edit(IntroCategory $introCategory): Response
    {
        return Inertia::render('Admin/IntroCategories/Edit', [
            'category' => $introCategory,
        ]);
    }

    public function update(Request $request, IntroCategory $introCategory)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $introCategory->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect('/admin/intro-categories')->with('success', '分類已成功更新');
    }

    public function destroy(IntroCategory $introCategory)
    {
        $introCategory->delete();

        return redirect('/admin/intro-categories')->with('success', '分類已成功刪除');
    }
}
