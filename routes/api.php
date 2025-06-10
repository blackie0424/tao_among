<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/fish', [FishController::class, 'getFishs']);
Route::post('/fish', [FishController::class, 'create']);
Route::get('/fish/{id}', [FishController::class, 'getFishById'])->whereNumber('id');
Route::get('/fish/{id}/notes', [FishController::class, 'getFishNotesSince'])->whereNumber('id');

// 新增更新魚類資料的路由
Route::put('/fish/{id}', [FishController::class, 'update'])->whereNumber('id');

Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::post('/fish/{id}/note', [FishController::class, 'addFishNote']);
Route::post('/supabase/signed-upload-url', [UploadController::class, 'getSignedUploadUrl']);
