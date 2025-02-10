<?php

namespace App\Http\Controllers;

use App\Services\UploadService;

class UploadController extends Controller
{
    public function uploadImage()
    {
        $request = request();
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'image upload failed', 'data' => $e->errors()], 400);
        } catch (Exception $e) {
            return response()->json(['message' => 'Upload failed', 'error' => $e->getMessage()], 500);
        }

        $uploadService = new UploadService;
        $imageName = $uploadService->uploadImage($request);

        return response()->json([
            'message' => $imageName ? 'image uploaded successfully' : 'Upload failed',
            'data' => $imageName ? $imageName : null],
            201
        );
    }
}
