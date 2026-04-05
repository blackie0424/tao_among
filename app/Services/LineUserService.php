<?php

namespace App\Services;

use App\Contracts\LineUserServiceInterface;
use App\Contracts\RichMenuServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LineUserService implements LineUserServiceInterface
{
    public function __construct(
        protected RichMenuServiceInterface $richMenuService
    ) {
    }

    public function upsert(string $lineUserId, string $displayName, ?string $pictureUrl = null): User
    {
        $existing = User::where('line_user_id', $lineUserId)->first();

        if ($existing) {
            // 更新時只更新名稱和大頭照，不覆蓋 role
            $data = ['name' => $displayName];
            if ($pictureUrl !== null) {
                $data['picture_url'] = $pictureUrl;
            }
            $existing->update($data);
            return $existing->fresh();
        }

        $user = User::create([
            'line_user_id' => $lineUserId,
            'name'         => $displayName,
            'picture_url'  => $pictureUrl,
            'source'       => 'line',
            'role'         => 'viewer',
        ]);

        Log::info('LineUserService: upserted user', [
            'lineUserId'  => $lineUserId,
            'displayName' => $displayName,
        ]);

        return $user->fresh();
    }

    public function assignRole(string $lineUserId, string $role): User
    {
        $user = User::where('line_user_id', $lineUserId)->firstOrFail();
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
        return User::where('line_user_id', $lineUserId)->value('role') ?? 'viewer';
    }
}
