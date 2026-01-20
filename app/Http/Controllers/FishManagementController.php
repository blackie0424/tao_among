<?php

namespace App\Http\Controllers;

use App\Services\FishService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class FishManagementController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * 影音紀錄管理頁面 (整合照片與錄音)
     */
    public function mediaManager($id)
    {
        $details = $this->fishService->getFishDetails((int) $id);
        return Inertia::render('Fish/MediaManager', $details);
    }

    /**
     * 知識筆記管理頁面 (整合地方知識與進階知識)
     */
    public function knowledgeManager($id)
    {
        $details = $this->fishService->getFishDetails((int) $id);
        // FishService 的 getFishDetails 已包含 fishNotes 與 tribalClassifications
        return Inertia::render('Fish/KnowledgeManager', $details);
    }
}
