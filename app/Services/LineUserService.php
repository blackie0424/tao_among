<?php

namespace App\Services;

use App\Contracts\LineUserServiceInterface;
use App\Contracts\RichMenuServiceInterface;
use App\Models\LineUser;
use Illuminate\Support\Facades\Log;

class LineUserService implements LineUserServiceInterface
{
    public function __construct(
        protected RichMenuServiceInterface $richMenuService
    ) {
    }

    public function upsert(string $lineUserId, string $displayName, ?string $pictureUrl = null): LineUser
    {
        $user = LineUser::updateOrCreate(
            ['line_user_id' => $lineUserId],
            array_filter([
                'display_name' => $displayName,
                'picture_url'  => $pictureUrl,
            ], fn ($v) => $v !== null)
        );

        Log::info('LineUserService: upserted user', [
            'lineUserId'  => $lineUserId,
            'displayName' => $displayName,
        ]);

        return $user->fresh();
    }

    public function assignRole(string $lineUserId, string $role): LineUser
    {
        $user = LineUser::where('line_user_id', $lineUserId)->firstOrFail();
        $user->update(['role' => $role]);

        if (in_array($role, ['editor', 'admin'])) {
            $editorMenuId = config('line.editor_rich_menu_id');
            if ($editorMenuId) {
                $this->richMenuService->linkToUser($lineUserId, $editorMenuId);
            }
        } else {
            $this->richMenuService->unlinkFromUser($lineUserId);
        }

        Log::info('LineUserService: assigned role', [
            'lineUserId' => $lineUserId,
            'role'       => $role,
        ]);

        return $user->fresh();
    }

    public function getRole(string $lineUserId): string
    {
        return LineUser::where('line_user_id', $lineUserId)->value('role') ?? 'viewer';
    }
}
