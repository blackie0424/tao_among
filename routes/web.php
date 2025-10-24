<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\FishAudioController;
use App\Http\Controllers\KnowledgeHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FishController::class, 'index']);
Route::get('/fishs', [FishController::class, 'getFishs']);
Route::get('/search', [FishController::class, 'search'])->name('fish.search');

Route::get('/fish/create', [FishController::class, 'create'])->name('fish.create');
Route::post('/fish', [FishController::class, 'store'])->name('fish.store');

Route::get('/fish/{id}', [FishController::class, 'getFish']);
Route::get('/fish/{id}/createAudio', [FishController::class,'createAudio']);



Route::get('/fish/{id}/create', [FishNoteController::class,'create']);
Route::get('/fish/{id}/edit', [FishController::class, 'edit'])->name('fish.edit');
Route::put('/fish/{id}/name', [FishController::class, 'updateName'])->name('fish.updateName');
Route::delete('/fish/{id}', [FishController::class, 'destroy'])->name('fish.destroy');
Route::get('/fish/{id}/editSize', [FishController::class, 'editSize'])->name('fish.editSize');
Route::put('/fish/{id}/size', [FishController::class, 'updateSize'])->name('fish.updateSize');

// 捕獲紀錄路由
Route::get('/fish/{id}/capture-records', [FishController::class, 'captureRecords'])->name('fish.capture-records');
Route::get('/fish/{id}/capture-records/create', [FishController::class, 'createCaptureRecord'])->name('fish.capture-records.create');
Route::post('/fish/{id}/capture-records', [FishController::class, 'storeCaptureRecord'])->name('fish.capture-records.store');
Route::get('/fish/{id}/capture-records/{record_id}/edit', [FishController::class, 'editCaptureRecord'])->name('fish.capture-records.edit');
Route::put('/fish/{id}/capture-records/{record_id}', [FishController::class, 'updateCaptureRecord'])->name('fish.capture-records.update');
Route::delete('/fish/{id}/capture-records/{record_id}', [FishController::class, 'destroyCaptureRecord'])->name('fish.capture-records.destroy');

// 知識管理路由
Route::get('/fish/{id}/knowledge', [KnowledgeHubController::class, 'index']);

// 地方知識路由
Route::get('/fish/{id}/tribal-classifications', [FishController::class, 'tribalClassifications'])->name('fish.tribal-classifications');
Route::get('/fish/{id}/tribal-classifications/create', [FishController::class, 'createTribalClassification'])->name('fish.tribal-classifications.create');
Route::post('/fish/{id}/tribal-classifications', [FishController::class, 'storeTribalClassification'])->name('fish.tribal-classifications.store');
Route::get('/fish/{id}/tribal-classifications/{classification_id}/edit', [FishController::class, 'editTribalClassification'])->name('fish.tribal-classifications.edit');
Route::put('/fish/{id}/tribal-classifications/{classification_id}', [FishController::class, 'updateTribalClassification'])->name('fish.tribal-classifications.update');
Route::delete('/fish/{id}/tribal-classifications/{classification_id}', [FishController::class, 'destroyTribalClassification'])->name('fish.tribal-classifications.destroy');


// 進階知識管理路由
Route::get('/fish/{fish}/knowledge-list', [FishNoteController::class, 'knowledgeList'])->name('fish.knowledge-list');
Route::get('/fish/{fish}/knowledge/{note}/edit', [FishNoteController::class, 'editKnowledge'])->name('fish.knowledge.edit');
Route::put('/fish/{fish}/knowledge/{note}', [FishNoteController::class, 'updateKnowledge'])->name('fish.knowledge.update');
Route::delete('/fish/{fish}/knowledge/{note}', [FishNoteController::class, 'destroyKnowledge'])->name('fish.knowledge.destroy');

// 發音列表管理路由
Route::get('/fish/{fish}/audio-list', [FishAudioController::class, 'audioList'])->name('fish.audio-list');
Route::put('/fish/{fish}/audio/{audio}', [FishController::class, 'updateAudioFilename']);
Route::delete('/fish/{fish}/audio/{audio}', [FishAudioController::class, 'destroyAudio'])->name('fish.audio.destroy');
