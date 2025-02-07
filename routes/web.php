<?php

use App\Http\Controllers\FishController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FishController::class, 'index']);

Route::get('/fishs', [FishController::class, 'getFishs']);
Route::get('/fish/{id}', [FishController::class, 'getFish']);
