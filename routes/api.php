<?php

use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\LineBotController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\TribalClassificationController;
use App\Http\Controllers\FishMergeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Health check endpoint for network status verification
Route::get('/health-check', function () {
    return response()->json(['status' => 'ok'], 200);
});

// LINE Bot Webhook
Route::post('/line/webhook', [LineBotController::class, 'webhook']);

// =====================================================
// 公開唯讀路由（不需登入）
// =====================================================

Route::get('/fish', [ApiFishController::class, 'getFishs']);
Route::get('/capture-records', [ApiFishController::class, 'getAllCaptureRecords']);
Route::get('/fishs/search', [ApiFishController::class, 'search']);
Route::get('/fishs/random-unknown', [ApiFishController::class, 'randomUnknownFish']);
Route::get('/fishs/filter', [ApiFishController::class, 'getFishesByFilter']);
Route::get('/fishs/random', [ApiFishController::class, 'getRandomFishes']);
Route::get('/fish/{id}', [ApiFishController::class, 'getFishById'])->whereNumber('id');
Route::get('/fish/{id}/compact', [ApiFishController::class, 'getCompactFishById'])->whereNumber('id');
Route::get('/fish/{id}/notes', [ApiFishController::class, 'getFishNotes'])->whereNumber('id');

Route::get('/fish/{fish_id}/tribal-classifications', [TribalClassificationController::class, 'index'])->whereNumber('fish_id');
Route::get('/tribal-classifications/{id}', [TribalClassificationController::class, 'show'])->whereNumber('id');

// =====================================================
// 需要登入且具備 editor/admin 角色的寫入路由
// =====================================================

Route::middleware(['auth:sanctum', 'editor'])->group(function () {

    // 魚類管理
    Route::post('/fish', [ApiFishController::class, 'store']);
    Route::put('/fish/{id}', [ApiFishController::class, 'update'])->whereNumber('id');
    Route::delete('/fish/{id}', [ApiFishController::class, 'destroy'])->whereNumber('id');

    // 知識筆記
    Route::post('/fish/{id}/note', [FishNoteController::class, 'store'])->whereNumber('id');
    Route::put('/fish/{id}/note/{note_id}', [FishNoteController::class, 'update'])
        ->whereNumber('id')
        ->whereNumber('note_id');
    Route::delete('/fish/{id}/note/{note_id}', [FishNoteController::class, 'destroy'])
        ->whereNumber('id')
        ->whereNumber('note_id');

    // 部落分類
    Route::post('/fish/{fish_id}/tribal-classifications', [TribalClassificationController::class, 'store'])->whereNumber('fish_id');
    Route::put('/tribal-classifications/{id}', [TribalClassificationController::class, 'update'])->whereNumber('id');
    Route::delete('/tribal-classifications/{id}', [TribalClassificationController::class, 'destroy'])->whereNumber('id');

    // 魚類合併
    Route::post('/fish/merge/preview', [FishMergeController::class, 'preview']);
    Route::post('/fish/merge', [FishMergeController::class, 'merge']);

    // 上傳與 signed URL
    Route::post('/upload', [UploadController::class, 'uploadImage']);
    Route::post('/upload-audio', [UploadController::class, 'uploadAudio']);
    Route::post('/upload/audio/sign', [UploadController::class, 'signPendingAudio']);
    Route::post('/storage/signed-upload-url', [UploadController::class, 'getSignedUploadUrl']);
    Route::post('/fish/{id}/storage/signed-upload-audio-url', [UploadController::class, 'getSignedUploadAudioUrl'])->whereNumber('id');
});
