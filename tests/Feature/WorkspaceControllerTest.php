<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('GET /workspace', function () {
    it('editor 可存取工作區並取得工作清單', function () {
        $editor = User::factory()->create();
        Fish::factory()->count(3)->create(['audio_filename' => null]);

        $this->actingAs($editor, 'sanctum')
            ->get('/workspace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EditorHome')
                ->has('needAudio')
                ->has('needPhoto')
                ->has('recentEdits')
                ->has('limit')
            );
    });

    it('viewer 無法存取工作區（403）', function () {
        $viewer = User::factory()->lineViewer()->create();

        $this->actingAs($viewer, 'sanctum')
            ->get('/workspace')
            ->assertForbidden();
    });

    it('未登入無法存取工作區（redirect）', function () {
        $this->get('/workspace')
            ->assertRedirect();
    });

    it('limit 參數控制 needAudio 與 needPhoto 筆數上限', function () {
        $editor = User::factory()->create();
        Fish::factory()->count(25)->create(['audio_filename' => null]);

        $this->actingAs($editor, 'sanctum')
            ->get('/workspace?limit=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EditorHome')
                ->where('needAudio', fn ($items) => count($items) <= 10)
                ->where('limit', 10)
            );
    });

    it('limit 超過上限時夾緊至 50', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor, 'sanctum')
            ->get('/workspace?limit=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('limit', 50)
            );
    });

    it('limit 低於下限時夾緊至 10', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor, 'sanctum')
            ->get('/workspace?limit=0')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('limit', 10)
            );
    });

    it('未傳入 limit 時使用預設值 20', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor, 'sanctum')
            ->get('/workspace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('limit', 20)
            );
    });

    it('首頁 / 對 editor 不再渲染 EditorHome', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor, 'sanctum')
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Index'));
    });
});
