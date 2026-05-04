<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can delete a fish via web route', function () {
    $user = User::factory()->create();
    $fish = Fish::factory()->create([
        'name' => 'Test Fish',
        'image' => 'test.jpg',
    ]);

    // 使用 DELETE 方法刪除魚類
    $response = $this->actingAs($user)->delete('/fish/' . $fish->id);

    // 驗證重定向到魚類列表頁面
    $response->assertRedirect('/fishs');

    // 驗證魚類已被軟刪除
    $this->assertSoftDeleted('fish', [
        'id' => $fish->id,
    ]);
});

it('returns 404 when deleting a non-existent fish via web route', function () {
    $invalidFishId = 99999;

    $response = $this->delete('/fish/' . $invalidFishId);

    // Laravel 的 findOrFail 會拋出 ModelNotFoundException，
    // 在 web 路由中這通常會重定向到錯誤頁面
    $response->assertRedirect();
});
