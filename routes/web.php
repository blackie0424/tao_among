<?php

use App\Http\Controllers\FishController;
use App\Http\Controllers\FishNoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FishController::class, 'index']);
Route::get('/fishs', [FishController::class, 'getFishs']);

Route::get('/fish/create', [FishController::class, 'create'])->name('fish.create');
Route::post('/fish', [FishController::class, 'store'])->name('fish.store');

Route::get('/fish/{id}', [FishController::class, 'getFish']);
Route::get('/fish/{id}/createAudio', [FishController::class,'createAudio']);



Route::get('/fish/{id}/create', [FishNoteController::class,'create']);
Route::get('/fish/{id}/edit', [FishController::class, 'edit'])->name('fish.edit');
Route::get('/fish/{id}/editSize', [FishController::class, 'editSize'])->name('fish.editSize');
Route::get('/fish/{id}/tribal-classifications', [FishController::class, 'tribalClassifications'])->name('fish.tribal-classifications');
Route::post('/fish/{id}/tribal-classifications', [FishController::class, 'storeTribalClassification'])->name('fish.tribal-classifications.store');
Route::put('/fish/{id}/tribal-classifications/{classification_id}', [FishController::class, 'updateTribalClassification'])->name('fish.tribal-classifications.update');
Route::delete('/fish/{id}/tribal-classifications/{classification_id}', [FishController::class, 'destroyTribalClassification'])->name('fish.tribal-classifications.destroy');
