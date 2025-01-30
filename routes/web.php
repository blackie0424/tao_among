<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FishController;


Route::get('/', [FishController::class, 'index']);

Route::get('/api/fish/{id}', [FishController::class, 'getFishById']);
Route::get('/fishs', [FishController::class, 'getFishs']);

Route::get('/fish/{id}', [FishController::class, 'getFish']);




// Route::prefix('api')->group(function () {
//     Route::get('/fish/list/', [FishController::class, 'listFish']);
//     Route::get('/fish/{id}', [FishController::class, 'getFishById']);
//     Route::get('/fish/{id}/basic', [FishController::class, 'getFishBasic']);
// });