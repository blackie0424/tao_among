<?php

namespace Tests\Scripts;

use App\Models\Fish;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Script to verify LINE audio upload metadata in S3
 *
 * Usage:
 * php artisan tinker
 * require 'tests/Scripts/VerifyLineAudioMetadata.php';
 * Tests\Scripts\VerifyLineAudioMetadata::verify();
 *
 * Or verify specific fish:
 * Tests\Scripts\VerifyLineAudioMetadata::verifyFish($fishId);
 */
class VerifyLineAudioMetadata
{
    /**
     * Verify the most recent fish with audio
     */
    public static function verify(): void
    {
        echo "\n=== LINE Audio Upload Verification ===\n\n";
        
        $fish = Fish::whereNotNull('audio_filename')
            ->orderBy('updated_at', 'desc')
            ->first();
            
        if (!$fish) {
            echo "❌ No fish found with audio\n";
            return;
        }
        
        self::verifyFish($fish->id);
    }
    
    /**
     * Verify specific fish audio
     */
    public static function verifyFish(int $fishId): void
    {
        $fish = Fish::find($fishId);
        
        if (!$fish) {
            echo "❌ Fish not found: {$fishId}\n";
            return;
        }
        
        echo "Fish ID: {$fish->id}\n";
        echo "Fish Name: {$fish->chinese_name}\n";
        echo "Updated At: {$fish->updated_at}\n\n";
        
        if (!$fish->audio_filename) {
            echo "❌ Fish has no audio_filename\n";
            return;
        }
        
        echo "--- Database Information ---\n";
        echo "Audio Filename: {$fish->audio_filename}\n";
        echo "Audio Duration: " . ($fish->audio_duration ?? 'NULL') . " ms\n";
        
        // Validate filename format
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/', $fish->audio_filename)) {
            echo "✓ Filename format is valid (UUID.m4a)\n";
        } else {
            echo "❌ Filename format is invalid\n";
        }
        
        // Validate duration
        if ($fish->audio_duration !== null) {
            if ($fish->audio_duration >= 0 && $fish->audio_duration <= 5100) {
                echo "✓ Duration is within valid range (0-5100ms)\n";
            } else {
                echo "⚠️  Duration is outside expected range: {$fish->audio_duration}ms\n";
            }
        } else {
            echo "⚠️  Duration is NULL\n";
        }
        
        echo "\n--- S3 Information ---\n";
        
        $path = 'audio/' . $fish->audio_filename;
        $s3 = Storage::disk('s3');
        
        // Check existence
        if (!$s3->exists($path)) {
            echo "❌ File does not exist in S3: {$path}\n";
            return;
        }
        echo "✓ File exists in S3\n";
        
        // Get file size
        try {
            $size = $s3->size($path);
            echo "File Size: " . number_format($size) . " bytes (" . self::formatBytes($size) . ")\n";
            
            if ($size < 100) {
                echo "❌ File is too small (< 100 bytes)\n";
            } elseif ($size > 1024 * 1024) {
                echo "⚠️  File is larger than 1MB\n";
            } else {
                echo "✓ File size is reasonable\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error getting file size: {$e->getMessage()}\n";
        }
        
        // Get URL
        try {
            $url = $s3->url($path);
            echo "URL: {$url}\n";
            echo "✓ URL generated successfully\n";
        } catch (\Exception $e) {
            echo "❌ Error generating URL: {$e->getMessage()}\n";
        }
        
        // Check metadata using AWS SDK
        echo "\n--- S3 Metadata ---\n";
        try {
            $adapter = $s3->getAdapter();
            $client = $adapter->getClient();
            $bucket = config('filesystems.disks.s3.bucket');
            
            $result = $client->headObject([
                'Bucket' => $bucket,
                'Key' => $path,
            ]);
            
            $contentType = $result['ContentType'] ?? 'NOT SET';
            echo "Content-Type: {$contentType}\n";
            
            if (in_array($contentType, ['audio/mp4', 'audio/m4a', 'audio/x-m4a'])) {
                echo "✓ Content-Type is correct for M4A audio\n";
            } else {
                echo "❌ Content-Type is incorrect (expected audio/mp4 or audio/m4a)\n";
            }
            
            $cacheControl = $result['CacheControl'] ?? 'NOT SET';
            echo "Cache-Control: {$cacheControl}\n";
            
            $contentLength = $result['ContentLength'] ?? 0;
            echo "Content-Length: " . number_format($contentLength) . " bytes\n";
            
            if ($contentLength === $size) {
                echo "✓ Content-Length matches file size\n";
            } else {
                echo "⚠️  Content-Length mismatch\n";
            }
            
            // Check if publicly accessible
            $metadata = $result['Metadata'] ?? [];
            echo "Metadata: " . json_encode($metadata) . "\n";
            
        } catch (\Exception $e) {
            echo "❌ Error getting S3 metadata: {$e->getMessage()}\n";
            echo "This might be due to missing AWS SDK or permissions\n";
        }
        
        echo "\n--- Verification Summary ---\n";
        echo "Database: " . ($fish->audio_filename ? "✓" : "❌") . "\n";
        echo "S3 File: " . ($s3->exists($path) ? "✓" : "❌") . "\n";
        echo "URL: " . (isset($url) ? "✓" : "❌") . "\n";
        
        echo "\n--- Next Steps ---\n";
        echo "1. Test the URL in a browser: {$url}\n";
        echo "2. Check if audio plays correctly\n";
        echo "3. Verify audio content matches what was recorded\n";
        echo "4. Check web interface for playback functionality\n\n";
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }
    }
    
    /**
     * List all fish with audio
     */
    public static function listAll(): void
    {
        echo "\n=== All Fish with Audio ===\n\n";
        
        $fishes = Fish::whereNotNull('audio_filename')
            ->orderBy('updated_at', 'desc')
            ->get();
            
        if ($fishes->isEmpty()) {
            echo "No fish found with audio\n";
            return;
        }
        
        echo "Total: {$fishes->count()} fish\n\n";
        
        foreach ($fishes as $fish) {
            echo "ID: {$fish->id} | {$fish->chinese_name} | {$fish->audio_filename} | {$fish->audio_duration}ms | {$fish->updated_at}\n";
        }
        
        echo "\nUse verifyFish(\$id) to check specific fish\n\n";
    }
}
