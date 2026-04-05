<?php

namespace App\Http\Controllers;

use App\Contracts\LineUserServiceInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LineUserController extends Controller
{
    public function __construct(
        protected LineUserServiceInterface $lineUserService
    ) {
    }

    public function index(): Response
    {
        $lineUsers = User::where('source', 'line')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('LineUsers', [
            'lineUsers' => $lineUsers,
        ]);
    }

    public function updateRole(Request $request, User $lineUser): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:viewer,editor'],
        ]);

        $updated = $this->lineUserService->assignRole(
            $lineUser->line_user_id,
            $validated['role']
        );

        return response()->json([
            'id'           => $updated->id,
            'line_user_id' => $updated->line_user_id,
            'display_name' => $updated->name,
            'role'         => $updated->role,
        ]);
    }
}
