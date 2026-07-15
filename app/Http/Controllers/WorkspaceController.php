<?php

namespace App\Http\Controllers;

use App\Models\Fish;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    private const VALID_LIMITS = [10, 20, 30, 50];
    private const DEFAULT_LIMIT = 20;

    public function index(Request $request): Response
    {
        $limit = $this->resolveLimit($request->query('limit'));
        $cols = ['id', 'name', 'image'];

        $needAudio = Fish::doesntHave('audios')
            ->orderBy('id')
            ->limit($limit)
            ->get($cols)
            ->map(fn ($f) => [
                'id'        => $f->id,
                'name'      => $f->name,
                'image_url' => $f->image_url,
            ]);

        $needPhoto = Fish::where(function ($q) {
                $q->whereNull('image')
                  ->orWhere('image', '')
                  ->orWhere('image', 'default.png');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get($cols)
            ->map(fn ($f) => [
                'id'        => $f->id,
                'name'      => $f->name,
                'image_url' => null,
            ]);

        $recentEdits = Fish::orderByDesc('updated_at')
            ->limit(10)
            ->get($cols)
            ->map(fn ($f) => [
                'id'        => $f->id,
                'name'      => $f->name,
                'image_url' => $f->image_url,
            ]);

        return Inertia::render('EditorHome', [
            'needAudio'   => $needAudio,
            'needPhoto'   => $needPhoto,
            'recentEdits' => $recentEdits,
            'limit'       => $limit,
        ]);
    }

    private function resolveLimit(mixed $value): int
    {
        $int = (int) $value;
        return in_array($int, self::VALID_LIMITS, true) ? $int : self::DEFAULT_LIMIT;
    }
}
