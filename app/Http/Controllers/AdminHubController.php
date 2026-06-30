<?php

namespace App\Http\Controllers;

use App\Models\Fish;
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

        $pendingUsers = User::where('role', 'viewer')->count();

        return Inertia::render('Admin/Hub', [
            'stats' => [
                'fishCount'     => $totalFish,
                'audioCoverage' => $audioCoverage,
                'pendingAudio'  => $pendingAudio,
                'monthlyNew'    => $monthlyNew,
            ],
            'pendingUsers' => $pendingUsers,
        ]);
    }
}
