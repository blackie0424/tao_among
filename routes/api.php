<?php

use App\Http\Controllers\ApiFishController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FishNoteController;
use App\Http\Controllers\FishSizeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 將 fish 相關 API 路由指向 ApiFishController
Route::get('/fish', [ApiFishController::class, 'getFishs']);
Route::post('/fish', [ApiFishController::class, 'store']);
Route::get('/fish/{id}', [ApiFishController::class, 'getFishById'])->whereNumber('id');
Route::delete('/fish/{id}', [ApiFishController::class, 'destroy'])->whereNumber('id');
Route::put('/fish/{id}', [ApiFishController::class, 'update'])->whereNumber('id');
Route::get('/fish/{id}/notes', [ApiFishController::class, 'getFishNotes'])->whereNumber('id');

// 其他 API
Route::post('/upload', [UploadController::class, 'uploadImage']);
Route::post('/supabase/signed-upload-url', [UploadController::class, 'getSignedUploadUrl']);

Route::post('/upload-audio', [UploadController::class, 'uploadAudio']);
Route::post('/fish/{id}/supabase/signed-upload-audio-url', [UploadController::class, 'getSignedUploadAudioUrl'])->whereNumber('id');


Route::post('/fish/{id}/note', [FishNoteController::class, 'store'])->whereNumber('id');
Route::put('/fish/{id}/note/{note_id}', [FishNoteController::class, 'update'])
    ->whereNumber('id')
    ->whereNumber('note_id');
Route::delete('/fish/{id}/note/{note_id}', [FishNoteController::class, 'destroy'])
    ->whereNumber('id')
    ->whereNumber('note_id');

Route::get('/fishSize/{fish_id}', [FishSizeController::class, 'show']);
Route::post('/fishSize', [FishSizeController::class, 'store']);



// Route to trigger the cron job manually
Route::get('/schedule-run', function () {
    Log::info('Cron job triggered at ' . now()->toDateTimeString());
    $output = Artisan::call('schedule:run');
    return response()->json([
        'message' => 'Cron job executed successfully',
        'output' => Artisan::output()
    ]);
});
