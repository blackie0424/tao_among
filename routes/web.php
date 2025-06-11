<?php

use App\Http\Controllers\FishController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FishController::class, 'index']);
Route::get('/fish/create', [FishController::class, 'create'])->name('fish.create');
Route::post('/fish', [FishController::class, 'store'])->name('fish.store');

Route::get('/fishs', [FishController::class, 'getFishs']);
Route::get('/fish/{id}', [FishController::class, 'getFish']);

