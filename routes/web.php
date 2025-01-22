<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FishController;


Route::get('/', [FishController::class, 'index']);
Route::get('/list', [FishController::class, 'getList']);
Route::get('/fish/{id}', [FishController::class, 'getFish']);

