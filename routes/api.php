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

Route::post('/upload', [UploadController::class, 'uploadImage']);
