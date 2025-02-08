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
Route::post('/fish/upload', [UploadController::class, 'uploadImage']);
Route::get('/fish/{id}', [FishController::class, 'getFishById']);
