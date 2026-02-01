<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\FishAudioController;
use App\Http\Controllers\KnowledgeHubController;
use App\Http\Controllers\CaptureRecordController;
use App\Http\Controllers\TribalClassificationController;
use App\Http\Controllers\FishManagementController;

use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Route;

// =====================================================
// 公開路由（不需登入）
// =====================================================

// 登入相關
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

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
    // 注意：/fish/create 必須在 /fish/{id} 之前定義，否則會被當作 id 參數
    Route::get('/fish/create', [FishController::class, 'create'])->name('fish.create');
    Route::post('/fish', [FishController::class, 'store'])->name('fish.store');
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
    Route::get('/fish/{id}/tribal-classifications/{classification_id}/edit', [TribalClassificationController::class, 'editPage'])->name('fish.tribal-classifications.edit');
    Route::put('/fish/{id}/tribal-classifications/{classification_id}', [TribalClassificationController::class, 'updatePage'])->name('fish.tribal-classifications.update');
    Route::delete('/fish/{id}/tribal-classifications/{classification_id}', [TribalClassificationController::class, 'destroyPage'])->name('fish.tribal-classifications.destroy');

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

    // -------------------------------------------------
    // 發音管理
    // -------------------------------------------------
    Route::get('/fish/{id}/audio/create', [FishAudioController::class, 'create'])->name('fish.audio.create');
    Route::get('/fish/{id}/audio-list', [FishAudioController::class, 'audioList'])->name('fish.audio-list');
    Route::get('/fish/{id}/audio/{audio}/edit', [FishAudioController::class, 'editAudio'])->name('fish.audio.edit');
    Route::put('/fish/{id}/audio/{audio}', [FishAudioController::class, 'updateAudio'])->name('fish.audio.update');
    Route::put('/fish/{id}/audio/{audio}/set-base', [FishAudioController::class, 'updateAudioFilename'])->name('fish.audio.set-base');
    Route::delete('/fish/{id}/audio/{audio}', [FishAudioController::class, 'destroyAudio'])->name('fish.audio.destroy');
});

// 公開瀏覽頁面（魚類詳細頁需放在最後，避免與 /fish/create 等路由衝突）
Route::get('/fish/{id}', [FishController::class, 'getFish']);
