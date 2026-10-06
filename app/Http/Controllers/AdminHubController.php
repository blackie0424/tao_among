<?php

namespace App\Http\Controllers;

use App\Models\Fish;
use App\Models\TopicItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminHubController extends Controller
{
    public function index(): Response
    {
        $totalFish   = Fish::count();
        $withAudio   = Fish::whereHas('audios')->count();
        $pendingAudio = $totalFish - $withAudio;
        $monthlyNew  = Fish::where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $audioCoverage = $totalFish > 0 ? (int) round($withAudio / $totalFish * 100) : 0;

        $pendingUsers = User::where('role', 'guest')->count();

        return Inertia::render('Admin/Hub', [
            'stats' => [
                'fishCount'     => $totalFish,
                'audioCoverage' => $audioCoverage,
                'pendingAudio'  => $pendingAudio,
                'monthlyNew'    => $monthlyNew,
                'topicItemCount' => TopicItem::count(),
            ],
            'pendingUsers' => $pendingUsers,
        ]);
    }
}
