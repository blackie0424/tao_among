<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Fish;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    private const DEFAULT_LIMIT = 20;

    private const FISH_COLUMNS = ['id', 'name', 'image', 'display_capture_record_id'];

    public function index(Request $request): Response
    {
        $limit = $this->resolveLimit($request->query('limit'));

        return Inertia::render('EditorHome', [
            'needAudio' => $this->needAudio($limit),
            'needPhoto' => $this->needPhoto($limit),
            'recentEdits' => $this->recentEdits($limit),
            'pendingPlaces' => $this->pendingPlaces($limit),
            'limit' => $limit,
        ]);
    }

    private function needAudio(int $limit)
    {
        $fish = Fish::doesntHave('audios')
            ->with('displayCaptureRecord')
            ->orderBy('id')
            ->limit($limit)
            ->get(self::FISH_COLUMNS);

        return $fish->map(fn ($f) => $this->fishWithDisplayImage($f));
    }

    private function needPhoto(int $limit)
    {
        return Fish::where(function ($q) {
            $q->whereNull('image')
                ->orWhere('image', '')
                ->orWhere('image', 'default.png');
        })
            ->orderBy('id')
            ->limit($limit)
            ->get(self::FISH_COLUMNS)
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'image_url' => null,
            ]);
    }

    private function recentEdits(int $limit)
    {
        $fish = Fish::with('displayCaptureRecord')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(self::FISH_COLUMNS);

        return $fish->map(fn ($f) => $this->fishWithDisplayImage($f));
    }

    private function pendingPlaces(int $limit)
    {
        return CaptureSession::whereNull('place_id')
            ->orderByDesc('capture_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (CaptureSession $session) => [
                'id' => $session->id,
                'capture_date' => $session->capture_date->format('Y-m-d'),
                'tribe' => $session->tribe,
                'capture_method' => $session->capture_method,
                'location_hint' => $session->location_hint,
            ]);
    }

    /** 魚的精簡資料：照片與調整值同源（來自圖鑑主圖，沒有主圖時回退魚本身） */
    private function fishWithDisplayImage(Fish $fish): array
    {
        return [
            'id' => $fish->id,
            'name' => $fish->name,
            'image_url' => $fish->image_url,
            'display_image_url' => $fish->display_image_url,
            'display_image_framing' => $fish->display_image_framing,
        ];
    }

    private function resolveLimit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        return max(10, min(50, (int) $value));
    }
}
