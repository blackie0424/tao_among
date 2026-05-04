<?php

namespace App\Http\Controllers;

use App\Services\FishService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Exception;

class FishManagementController extends BaseController
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
        try {
            $details = $this->fishService->getFishDetails((int) $id);
            return Inertia::render('Fish/MediaManager', $details);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入媒體管理頁面');
        }
    }

    /**
     * 知識筆記管理頁面 (整合地方知識與進階知識)
     */
    public function knowledgeManager($id)
    {
        try {
            $details = $this->fishService->getFishDetails((int) $id);
            $details['tribes'] = config('fish_options.tribes');
            return Inertia::render('Fish/KnowledgeManager', $details);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入知識管理頁面');
        }
    }
}
