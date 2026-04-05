<?php

namespace App\Contracts;

use App\Models\LineUser;

interface LineUserServiceInterface
{
    /**
     * 根據 LINE userId 取得或建立使用者，並更新 display_name / picture_url
     */
    public function upsert(string $lineUserId, string $displayName, ?string $pictureUrl = null): LineUser;

    /**
     * 指派角色，並同步更新 LINE 圖文選單綁定
     */
    public function assignRole(string $lineUserId, string $role): LineUser;

    /**
     * 取得使用者角色，若不存在回傳 'viewer'
     */
    public function getRole(string $lineUserId): string;
}
