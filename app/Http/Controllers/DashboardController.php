<?php

namespace App\Http\Controllers;

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishNote;
use App\Models\TribalClassification;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // 魚類統計
        $fishStats = [
            'total' => Fish::count(),
            'with_capture_record' => Fish::has('captureRecords')->count(),
            'with_audio' => Fish::has('audios')->count(),
            'with_tribal_classification' => Fish::has('tribalClassifications')->count(),
        ];

        // 捕獲紀錄統計：總數 + 各部落分佈
        $captureStats = [
            'total' => CaptureRecord::count(),
            'by_tribe' => CaptureRecord::selectRaw('tribe, COUNT(*) as count')
                ->groupBy('tribe')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['tribe' => $row->tribe ?: '未分類', 'count' => $row->count])
                ->values(),
        ];

        // 部落分類統計：總數 + 各部落分佈
        $tribalStats = [
            'total' => TribalClassification::count(),
            'by_tribe' => TribalClassification::selectRaw('tribe, COUNT(*) as count')
                ->groupBy('tribe')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['tribe' => $row->tribe ?: '未分類', 'count' => $row->count])
                ->values(),
        ];

        // 音檔統計：總數 + 各 locate 分佈
        $audioStats = [
            'total' => FishAudio::count(),
            'by_locate' => FishAudio::selectRaw('locate, COUNT(*) as count')
                ->groupBy('locate')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['locate' => $row->locate ?: '未分類', 'count' => $row->count])
                ->values(),
        ];

        // 地方知識統計：總數 + 各類型分佈
        $noteStats = [
            'total' => FishNote::count(),
            'by_type' => FishNote::selectRaw('note_type, COUNT(*) as count')
                ->groupBy('note_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['type' => $row->note_type ?: '未分類', 'count' => $row->count])
                ->values(),
        ];

        // LINE 使用者統計：總數 + 各 role 分佈
        $userStats = [
            'total' => User::where('source', 'line')->count(),
            'by_role' => User::where('source', 'line')
                ->selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['role' => $row->role ?: '無角色', 'count' => $row->count])
                ->values(),
        ];

        return Inertia::render('Dashboard', [
            'fishStats'    => $fishStats,
            'captureStats' => $captureStats,
            'tribalStats'  => $tribalStats,
            'audioStats'   => $audioStats,
            'noteStats'    => $noteStats,
            'userStats'    => $userStats,
        ]);
    }
}
