<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FishNoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/fish', [FishController::class, 'getFishs']);
Route::post('/fish', [FishController::class, 'create']);
Route::get('/fish/{id}', [FishController::class, 'getFishById'])->whereNumber('id');
Route::delete('/fish/{id}', [FishController::class, 'destroy'])->whereNumber('id');

// 新增更新魚類資料的路由
Route::put('/fish/{id}', [FishController::class, 'update'])->whereNumber('id');

Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::post('/supabase/signed-upload-url', [UploadController::class, 'getSignedUploadUrl']);


Route::get('/fish/{id}/notes', [FishController::class, 'getFishNotesSince'])->whereNumber('id');
Route::post('/fish/{id}/note', [FishNoteController::class, 'store'])->whereNumber('id');
Route::put('/fish/{id}/note/{note_id}', [FishNoteController::class, 'update'])
    ->whereNumber('id')
    ->whereNumber('note_id');
Route::delete('/fish/{id}/note/{note_id}', [FishNoteController::class, 'destroy'])
    ->whereNumber('id')
    ->whereNumber('note_id');
