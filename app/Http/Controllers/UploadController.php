<?php

namespace App\Http\Controllers;

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

        $imagePath = $request->file('image')->store('images', 'public');
        $imageName = basename($imagePath);

        return response()->json(['message' => 'image uploaded successfully', 'data' => $imageName], 201);
    }
}
