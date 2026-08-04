<?php

namespace App\Http\Controllers;

use App\Contracts\StorageServiceInterface;
use App\Models\IntroCategory;
use App\Models\IntroSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IntroSlideController extends BaseController
{
    public function __construct(
        protected StorageServiceInterface $storageService
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/IntroSlides/Index', [
            'slides' => IntroSlide::with('category')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/IntroSlides/Create', [
            'categories' => IntroCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:intro_categories,id',
            'title'       => 'required|string|max:255',
            'body'        => 'nullable|string',
            'media_type'  => 'required|in:photo,youtube',
            'media_path'  => 'nullable|string|required_if:media_type,youtube',
            'photo'       => 'nullable|file|image|max:5120|required_if:media_type,photo',
            'sort_order'  => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $mediaPath = $data['media_path'] ?? null;

        if ($data['media_type'] === 'photo' && $request->hasFile('photo')) {
            $mediaPath = $this->uploadPhoto($request);
        }

        IntroSlide::create([
            'category_id'  => $data['category_id'] ?? null,
            'title'        => $data['title'],
            'body'         => $data['body'] ?? null,
            'media_type'   => $data['media_type'],
            'media_path'   => $mediaPath,
            'sort_order'   => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? false,
        ]);

        return redirect('/admin/intro-slides')->with('success', '投影片已成功建立');
    }

    public function edit(IntroSlide $introSlide): Response
    {
        return Inertia::render('Admin/IntroSlides/Edit', [
            'slide'      => $introSlide,
            'categories' => IntroCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, IntroSlide $introSlide)
    {
        $data = $request->validate([
            'category_id'  => 'nullable|exists:intro_categories,id',
            'title'        => 'required|string|max:255',
            'body'         => 'nullable|string',
            'media_type'   => 'required|in:photo,youtube',
            'media_path'   => 'nullable|string|required_if:media_type,youtube',
            'photo'        => 'nullable|file|image|max:5120',
            'sort_order'   => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
        ]);

        $mediaPath = $data['media_path'] ?? $introSlide->media_path;

        if ($data['media_type'] === 'photo' && $request->hasFile('photo')) {
            $mediaPath = $this->uploadPhoto($request);
        }

        $introSlide->update([
            'category_id'  => $data['category_id'] ?? null,
            'title'        => $data['title'],
            'body'         => $data['body'] ?? null,
            'media_type'   => $data['media_type'],
            'media_path'   => $mediaPath,
            'sort_order'   => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? false,
        ]);

        return redirect('/admin/intro-slides')->with('success', '投影片已成功更新');
    }

    public function destroy(IntroSlide $introSlide)
    {
        $introSlide->delete();

        return redirect('/admin/intro-slides')->with('success', '投影片已成功刪除');
    }

    public function togglePublished(IntroSlide $introSlide)
    {
        $introSlide->update(['is_published' => !$introSlide->is_published]);

        return redirect('/admin/intro-slides')->with('success', '發布狀態已更新');
    }

    private function uploadPhoto(Request $request): string
    {
        $file = $request->file('photo');

        if (app()->environment('local', 'testing')) {
            $filename = $file->store('intro-slides', 'public');
            return $filename;
        }

        return $this->storageService->uploadFile($file, 'intro-slides');
    }
}
