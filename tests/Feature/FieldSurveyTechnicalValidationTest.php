<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('GET /workspace/field-survey/technical-validation', function () {
    it('editor 可開啟田調錄音技術驗證頁', function () {
        $editor = User::factory()->lineEditor()->create();

        $this->actingAs($editor)
            ->get('/workspace/field-survey/technical-validation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FieldSurvey/TechnicalValidation')
            );
    });

    it('viewer 無法開啟田調錄音技術驗證頁', function () {
        $viewer = User::factory()->lineViewer()->create();

        $this->actingAs($viewer)
            ->get('/workspace/field-survey/technical-validation')
            ->assertForbidden();
    });

    it('未登入者會被導向登入頁', function () {
        $this->get('/workspace/field-survey/technical-validation')
            ->assertRedirect('/login');
    });
});
