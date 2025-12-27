<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\FishAudioController;
use App\Http\Controllers\KnowledgeHubController;
use App\Http\Controllers\CaptureRecordController;
use App\Http\Controllers\TribalClassificationController;
use App\Http\Controllers\FishSizeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FishController::class, 'index']);
Route::get('/fishs', [FishController::class, 'getFishs']);
Route::get('/search', [FishController::class, 'search'])->name('fish.search');

Route::get('/fish/create', [FishController::class, 'create'])->name('fish.create');
Route::post('/fish', [FishController::class, 'store'])->name('fish.store');

Route::get('/fish/{id}', [FishController::class, 'getFish']);
Route::get('/fish/{id}/createAudio', [FishAudioController::class,'create'])->name('fish.audio.create');



Route::get('/fish/{id}/create', [FishNoteController::class,'create']);
Route::get('/fish/{id}/edit', [FishController::class, 'edit'])->name('fish.edit');
Route::put('/fish/{id}/name', [FishController::class, 'updateName'])->name('fish.updateName');
Route::delete('/fish/{id}', [FishController::class, 'destroy'])->name('fish.destroy');

// Fish Size 路由
Route::get('/fish/{id}/editSize', [FishSizeController::class, 'edit'])->name('fish.editSize');
Route::put('/fish/{id}/size', [FishSizeController::class, 'update'])->name('fish.updateSize');

// 捕獲紀錄路由
Route::get('/fish/{id}/capture-records', [CaptureRecordController::class, 'index'])->name('fish.capture-records');
Route::get('/fish/{id}/capture-records/create', [CaptureRecordController::class, 'create'])->name('fish.capture-records.create');
Route::post('/fish/{id}/capture-records', [CaptureRecordController::class, 'store'])->name('fish.capture-records.store');
Route::get('/fish/{id}/capture-records/{record_id}/edit', [CaptureRecordController::class, 'edit'])->name('fish.capture-records.edit');
Route::put('/fish/{id}/capture-records/{record_id}', [CaptureRecordController::class, 'update'])->name('fish.capture-records.update');
Route::delete('/fish/{id}/capture-records/{record_id}', [CaptureRecordController::class, 'destroy'])->name('fish.capture-records.destroy');

// 知識管理路由
Route::get('/fish/{id}/knowledge', [KnowledgeHubController::class, 'index']);

// 地方知識路由（部落分類）
Route::get('/fish/{id}/tribal-classifications', [TribalClassificationController::class, 'indexPage'])->name('fish.tribal-classifications');
Route::get('/fish/{id}/tribal-classifications/create', [TribalClassificationController::class, 'createPage'])->name('fish.tribal-classifications.create');
Route::post('/fish/{id}/tribal-classifications', [TribalClassificationController::class, 'storePage'])->name('fish.tribal-classifications.store');
Route::get('/fish/{id}/tribal-classifications/{classification_id}/edit', [TribalClassificationController::class, 'editPage'])->name('fish.tribal-classifications.edit');
Route::put('/fish/{id}/tribal-classifications/{classification_id}', [TribalClassificationController::class, 'updatePage'])->name('fish.tribal-classifications.update');
Route::delete('/fish/{id}/tribal-classifications/{classification_id}', [TribalClassificationController::class, 'destroyPage'])->name('fish.tribal-classifications.destroy');


// 進階知識管理路由
Route::get('/fish/{fish}/knowledge-list', [FishNoteController::class, 'knowledgeList'])->name('fish.knowledge-list');
Route::get('/fish/{fish}/knowledge/{note}/edit', [FishNoteController::class, 'editKnowledge'])->name('fish.knowledge.edit');
Route::put('/fish/{fish}/knowledge/{note}', [FishNoteController::class, 'updateKnowledge'])->name('fish.knowledge.update');
Route::delete('/fish/{fish}/knowledge/{note}', [FishNoteController::class, 'destroyKnowledge'])->name('fish.knowledge.destroy');

// 發音列表管理路由
Route::get('/fish/{fish}/audio-list', [FishAudioController::class, 'audioList'])->name('fish.audio-list');
Route::get('/fish/{fish}/audio/{audio}/edit', [FishAudioController::class, 'editAudio'])->name('fish.audio.edit');
Route::put('/fish/{fish}/audio/{audio}', [FishAudioController::class, 'updateAudio'])->name('fish.audio.update');
// 設定主發音（獨立路由，避免影響一般更新音訊）
Route::put('/fish/{fish}/audio/{audio}/set-base', [FishAudioController::class, 'updateAudioFilename'])->name('fish.audio.set-base');
Route::delete('/fish/{fish}/audio/{audio}', [FishAudioController::class, 'destroyAudio'])->name('fish.audio.destroy');
