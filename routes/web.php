<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\FishAudioController;
use App\Http\Controllers\KnowledgeHubController;
use App\Http\Controllers\CaptureRecordController;
use App\Http\Controllers\TribalClassificationController;
use App\Http\Controllers\FishManagementController;
use App\Http\Controllers\FishReportController;
use App\Http\Controllers\LineUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LineLoginController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReferenceKnowledgeController;

use App\Http\Controllers\AdminHubController;
use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Route;

// =====================================================
// 公開路由（不需登入）
// =====================================================

// 登入相關
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

// LINE Login OAuth
Route::get('/auth/line', [LineLoginController::class, 'redirect'])->name('auth.line');
Route::get('/auth/line/callback', [LineLoginController::class, 'callback'])->name('auth.line.callback');
Route::get('/auth/line/complete', [LineLoginController::class, 'complete'])->name('auth.line.complete');

// 公開瀏覽頁面
Route::get('/', [FishController::class, 'index']);
Route::get('/fishs', [FishController::class, 'getFishs']);
Route::get('/search', [FishController::class, 'search'])->name('fish.search');

// =====================================================
// 需要登入的路由
// =====================================================
Route::middleware(['auth'])->group(function () {
    
    // -------------------------------------------------
    // 魚類基本管理
    // -------------------------------------------------
    // 注意：/fish/batch-create 必須在 /fish/{id} 之前定義
    Route::get('/fish/batch-create', [FishController::class, 'batchCreate'])->name('fish.batch-create');
    Route::post('/fish/batch-create', [FishController::class, 'batchStore'])->name('fish.batch-create.store');
    Route::get('/fish/{id}/edit', [FishController::class, 'edit'])->name('fish.edit');
    Route::put('/fish/{id}/name', [FishController::class, 'updateName'])->name('fish.updateName');
    Route::delete('/fish/{id}', [FishController::class, 'destroy'])->name('fish.destroy');
    Route::get('/fish/{id}/merge', [FishController::class, 'showMergePage'])->name('fish.merge.page');
    Route::put('/fish/{id}/display-image', [FishController::class, 'updateDisplayImage'])->name('fish.display-image.update');

    // -------------------------------------------------
    // 聚合管理頁面
    // -------------------------------------------------
    Route::get('/fish/{id}/media-manager', [FishManagementController::class, 'mediaManager'])->name('fish.media-manager');
    Route::get('/fish/{id}/knowledge-manager', [FishManagementController::class, 'knowledgeManager'])->name('fish.knowledge-manager');

    // -------------------------------------------------
    // 捕獲紀錄管理
    // -------------------------------------------------
    Route::get('/fish/{id}/capture-records', [CaptureRecordController::class, 'index'])->name('fish.capture-records');
    Route::get('/fish/{id}/capture-records/create', [CaptureRecordController::class, 'create'])->name('fish.capture-records.create');
    Route::get('/fish/{id}/capture-records/batch-create', [CaptureRecordController::class, 'batchCreate'])->name('fish.capture-records.batch-create');
    Route::post('/fish/{id}/capture-records', [CaptureRecordController::class, 'store'])->name('fish.capture-records.store');
    Route::get('/fish/{id}/capture-records/{record_id}/edit', [CaptureRecordController::class, 'edit'])->name('fish.capture-records.edit');
    Route::put('/fish/{id}/capture-records/{record_id}', [CaptureRecordController::class, 'update'])->name('fish.capture-records.update');
    Route::delete('/fish/{id}/capture-records/{record_id}', [CaptureRecordController::class, 'destroy'])->name('fish.capture-records.destroy');

    // -------------------------------------------------
    // 地方知識（部落分類）管理
    // -------------------------------------------------
    Route::get('/fish/{id}/tribal-classifications', [TribalClassificationController::class, 'indexPage'])->name('fish.tribal-classifications');
    Route::get('/fish/{id}/tribal-classifications/create', [TribalClassificationController::class, 'createPage'])->name('fish.tribal-classifications.create');
    Route::post('/fish/{id}/tribal-classifications', [TribalClassificationController::class, 'storePage'])->name('fish.tribal-classifications.store');

    // -------------------------------------------------
    // 進階知識管理
    // -------------------------------------------------
    Route::get('/fish/{id}/knowledge', [KnowledgeHubController::class, 'index']);
    Route::get('/fish/{id}/knowledge/create', [FishNoteController::class, 'create'])->name('fish.knowledge.create');
    Route::post('/fish/{id}/knowledge', [FishNoteController::class, 'storeKnowledge'])->name('fish.knowledge.store');
    Route::get('/fish/{id}/knowledge-list', [FishNoteController::class, 'knowledgeList'])->name('fish.knowledge-list');
    Route::get('/fish/{id}/knowledge/{note}/edit', [FishNoteController::class, 'editKnowledge'])->name('fish.knowledge.edit');
    Route::put('/fish/{id}/knowledge/{note}', [FishNoteController::class, 'updateKnowledge'])->name('fish.knowledge.update');
    Route::delete('/fish/{id}/knowledge/{note}', [FishNoteController::class, 'destroyKnowledge'])->name('fish.knowledge.destroy');

    Route::middleware(['editor'])->group(function () {
        Route::get('/fish/{id}/reference-knowledge', [ReferenceKnowledgeController::class, 'index'])->name('fish.reference-knowledge.index');
        Route::get('/fish/{id}/reference-knowledge/create', [ReferenceKnowledgeController::class, 'create'])->name('fish.reference-knowledge.create');
        Route::post('/fish/{id}/reference-knowledge', [ReferenceKnowledgeController::class, 'store'])->name('fish.reference-knowledge.store');
        Route::get('/fish/{id}/reference-knowledge/{knowledge}/edit', [ReferenceKnowledgeController::class, 'edit'])->name('fish.reference-knowledge.edit');
        Route::put('/fish/{id}/reference-knowledge/{knowledge}', [ReferenceKnowledgeController::class, 'update'])->name('fish.reference-knowledge.update');
        Route::delete('/fish/{id}/reference-knowledge/{knowledge}', [ReferenceKnowledgeController::class, 'destroy'])->name('fish.reference-knowledge.destroy');
    });

    // -------------------------------------------------
    // 發音管理
    // -------------------------------------------------
    Route::get('/fish/{id}/audio/create', [FishAudioController::class, 'create'])->name('fish.audio.create');
    Route::get('/fish/{id}/audio-list', [FishAudioController::class, 'audioList'])->name('fish.audio-list');
    Route::get('/fish/{id}/audio/{audio}/edit', [FishAudioController::class, 'editAudio'])->name('fish.audio.edit');
    Route::put('/fish/{id}/audio/{audio}', [FishAudioController::class, 'updateAudio'])->name('fish.audio.update');
    Route::put('/fish/{id}/audio/{audio}/set-base', [FishAudioController::class, 'updateAudioFilename'])->name('fish.audio.set-base');
    Route::delete('/fish/{id}/audio/{audio}', [FishAudioController::class, 'destroyAudio'])->name('fish.audio.destroy');

    // -------------------------------------------------
    // LINE 使用者管理（僅 admin 可存取）
    // -------------------------------------------------
    Route::middleware(['admin'])->group(function () {
        Route::get('/admin', [AdminHubController::class, 'index'])->name('admin.hub');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/fish-report', [FishReportController::class, 'index'])->name('fish-report');
        Route::get('/line-users', [LineUserController::class, 'index'])->name('line-users.index');
        Route::put('/line-users/{lineUser}/role', [LineUserController::class, 'updateRole'])->name('line-users.update-role');
        Route::get('/admin/references', [ReferenceController::class, 'index'])->name('admin.references.index');
        Route::get('/admin/references/create', [ReferenceController::class, 'create'])->name('admin.references.create');
        Route::post('/admin/references', [ReferenceController::class, 'store'])->name('admin.references.store');
        Route::get('/admin/references/{reference}/edit', [ReferenceController::class, 'edit'])->name('admin.references.edit');
        Route::put('/admin/references/{reference}', [ReferenceController::class, 'update'])->name('admin.references.update');
    });
});

// 公開瀏覽頁面（魚類詳細頁需放在最後，避免與 /fish/create 等路由衝突）
Route::get('/fish/{id}', [FishController::class, 'getFish']);
