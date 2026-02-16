# LINE Audio Upload Implementation Guide

## Overview

This document explains the special handling required for LINE Bot audio uploads and the critical importance of Content-Type configuration.

## Problem Background

### The Issue

When users recorded 5-second voice messages through LINE and uploaded them to S3, the audio files had no sound when played back in the web interface.

### Root Cause

The LINE audio upload was using `Storage::disk('s3')->put()` without specifying the `ContentType` option. This caused S3 to store files with the default Content-Type of `application/octet-stream`, which browsers cannot play as audio.

## LINE vs Web Upload Architecture

### Web Upload Flow (Direct Upload)

```
User Browser
    ↓ (1. Request presigned URL)
Backend API
    ↓ (2. Generate presigned URL with ContentType)
User Browser
    ↓ (3. Direct upload to S3 with ContentType)
S3 Storage ✓ (ContentType set automatically)
```

**Characteristics:**

- Frontend directly uploads to S3 using presigned URL
- Content-Type is automatically set by browser
- No backend bandwidth usage
- Faster upload speed

### LINE Upload Flow (Backend Proxy)

```
LINE Platform (stores user recording)
    ↓ (1. Webhook notification)
LineBotController
    ↓ (2. Download binary stream)
LineBotService
    ↓ (3. Upload to S3)
LineUploadService
    ↓ (4. Storage::put() with ContentType)
S3 Storage ✓ (ContentType must be set manually)
```

**Characteristics:**

- Backend must download from LINE API first
- Backend then uploads to S3
- Content-Type **must be manually specified**
- Uses backend bandwidth (no direct upload option)

## LINE Audio Format Details

### M4A Format Specifications

- **Container Format**: MPEG-4 Part 14 (MP4)
- **Audio Codec**: AAC (Advanced Audio Coding)
- **MIME Type**: `audio/mp4` or `audio/m4a`
- **File Signature**: Contains "ftyp" magic bytes in header
- **Maximum Duration**: 5 seconds (system allows 5.1s tolerance)

### Why M4A?

LINE chose M4A because:

- Excellent compression (small file size)
- High audio quality
- Wide browser support
- Standard format for mobile voice recording

## Critical: Content-Type Configuration

### The Problem Without Content-Type

```php
// ❌ WRONG: Missing ContentType
Storage::disk('s3')->put($path, $audioStream);
// Result: S3 stores with ContentType = 'application/octet-stream'
// Browser behavior: Downloads file instead of playing
```

### The Solution With Content-Type

```php
// ✅ CORRECT: Explicit ContentType
Storage::disk('s3')->put($path, $audioStream, [
    'visibility' => 'public',
    'ContentType' => 'audio/mp4',
    'CacheControl' => 'max-age=31536000',
]);
// Result: S3 stores with ContentType = 'audio/mp4'
// Browser behavior: Plays audio in HTML5 player
```

### Why This Matters

1. **Browser Behavior**: Browsers use Content-Type to determine how to handle files

   - `audio/mp4` → Play in audio player
   - `application/octet-stream` → Download to disk

2. **HTML5 Audio Element**: Requires correct MIME type

   ```html
   <audio src="file.m4a"></audio>
   <!-- Only works if Content-Type is audio/mp4 -->
   ```

3. **Media Player Detection**: Players check Content-Type before attempting playback

## Implementation Details

### LineUploadService Configuration

The service now includes comprehensive options for S3 uploads:

```php
$success = Storage::disk('s3')->put($fullPath, $audioStream, [
    // Allow public access without signed URLs
    'visibility' => 'public',

    // Critical: Set correct MIME type for browser playback
    'ContentType' => 'audio/mp4',

    // Cache for 1 year to improve performance
    'CacheControl' => 'max-age=31536000',
]);
```

### Audio Validation

The system validates audio files before upload:

```php
protected function validateAudioBlob(string $audioBlob): bool
{
    // Check minimum file size
    if (strlen($audioBlob) < 100) {
        return false;
    }

    // Check for M4A file signature (ftyp magic bytes)
    $header = substr($audioBlob, 0, 12);
    if (strpos($header, 'ftyp') === false) {
        Log::warning('Audio missing M4A signature');
    }

    return true;
}
```

### Duration Validation

Audio duration is validated with tolerance:

```php
// Maximum duration: 5000ms (5 seconds)
// Tolerance: 100ms (to account for encoding variations)
// Accepted range: 0-5100ms
if ($duration > 5100) {
    throw new \Exception('Audio duration exceeds 5 second limit');
}
```

## Error Handling Strategy

### Comprehensive Logging

Every step is logged for debugging:

```php
Log::info('LINE Upload: Starting audio upload', [
    'filename' => $filename,
    'path' => $fullPath,
    'data_size' => strlen($audioStream),
    'extension' => $extension,
]);
```

### AWS S3 Error Handling

Specific handling for S3 exceptions:

```php
try {
    $success = Storage::disk('s3')->put($fullPath, $audioStream, $options);
} catch (\Aws\S3\Exception\S3Exception $e) {
    Log::error('S3 exception', [
        'aws_error_code' => $e->getAwsErrorCode(),
        'aws_error_message' => $e->getAwsErrorMessage(),
        'status_code' => $e->getStatusCode(),
    ]);
    throw new \Exception('S3 upload failed: ' . $e->getAwsErrorMessage());
}
```

### Post-Upload Verification

Verify file actually exists after upload:

```php
if (!Storage::disk('s3')->exists($fullPath)) {
    throw new \Exception('Audio file not found in S3 after upload');
}
```

## Testing Strategy

### Unit Tests

Test core functionality:

- File name generation (UUID format)
- Content-Type configuration
- Error handling
- Logging completeness

### Integration Tests

Test complete flow:

1. Simulate LINE webhook event
2. Download audio from LINE API
3. Upload to S3 with correct Content-Type
4. Verify database update
5. Verify audio playback

### Manual Testing

Real-world validation:

1. Record voice in LINE app
2. Send to bot
3. Verify success message
4. Check web interface playback
5. Verify S3 metadata

See [manual-testing-guide.md](./manual-testing-guide.md) for detailed steps.

## Troubleshooting

### Audio Has No Sound

**Symptoms:**

- File downloads instead of playing
- Audio player shows error
- File size is correct but no playback

**Diagnosis:**

```bash
# Check S3 metadata
aws s3api head-object \
    --bucket YOUR_BUCKET \
    --key audio/YOUR_FILE.m4a

# Look for ContentType in output
```

**Solution:**

- Verify ContentType is `audio/mp4` or `audio/m4a`
- If wrong, re-upload with correct Content-Type
- Check LineUploadService has correct configuration

### Upload Fails

**Symptoms:**

- Exception thrown during upload
- Log shows S3 error

**Diagnosis:**

```bash
# Check application logs
tail -f storage/logs/laravel.log | grep "LINE Upload"
```

**Common Causes:**

1. **Credentials**: Invalid AWS credentials
2. **Permissions**: IAM role lacks S3 PutObject permission
3. **Bucket**: Incorrect bucket name or region
4. **Network**: Timeout or connection issues

**Solution:**

- Verify `.env` has correct AWS credentials
- Check IAM policy includes `s3:PutObject`
- Verify bucket name and region in `config/filesystems.php`

### Duration Validation Fails

**Symptoms:**

- Bot rejects audio with "exceeds 5 second limit"
- Audio is actually under 5 seconds

**Diagnosis:**

```php
// Check actual duration from LINE API
Log::info('Audio duration', ['duration' => $duration]);
```

**Solution:**

- System allows 5100ms (5.1s) tolerance
- LINE may report slightly longer duration due to encoding
- If consistently failing, adjust tolerance in LineBotController

## Best Practices

### 1. Always Set Content-Type

```php
// ✅ DO: Always specify ContentType
Storage::disk('s3')->put($path, $content, [
    'ContentType' => 'audio/mp4',
]);

// ❌ DON'T: Rely on default
Storage::disk('s3')->put($path, $content);
```

### 2. Validate Before Upload

```php
// ✅ DO: Validate content before uploading
if (strlen($audioStream) === 0) {
    throw new \Exception('Empty audio stream');
}

// ❌ DON'T: Upload without validation
Storage::disk('s3')->put($path, $audioStream);
```

### 3. Log Comprehensively

```php
// ✅ DO: Log all important information
Log::info('Upload started', [
    'filename' => $filename,
    'size' => strlen($content),
    'type' => $contentType,
]);

// ❌ DON'T: Skip logging
Storage::disk('s3')->put($path, $content);
```

### 4. Verify After Upload

```php
// ✅ DO: Verify file exists
if (!Storage::disk('s3')->exists($path)) {
    throw new \Exception('Upload verification failed');
}

// ❌ DON'T: Assume success
Storage::disk('s3')->put($path, $content);
```

## Performance Considerations

### Caching

Set appropriate cache headers:

```php
'CacheControl' => 'max-age=31536000'  // 1 year
```

Benefits:

- Reduces S3 bandwidth costs
- Faster playback for repeat visitors
- Lower latency

### File Size

M4A format provides excellent compression:

- 5 second audio ≈ 50-100 KB
- Much smaller than WAV or uncompressed formats
- Suitable for mobile bandwidth

### Async Processing

Consider queue for large volumes:

```php
// For high-traffic bots
dispatch(new ProcessLineAudio($audioStream, $fishId));
```

## Security Considerations

### Public Access

Files are set to public visibility:

```php
'visibility' => 'public'
```

**Rationale:**

- Audio is not sensitive information
- Simplifies playback (no signed URLs needed)
- Better performance (CDN caching)

**Alternative:**
If privacy is required, use signed URLs:

```php
'visibility' => 'private'
// Then generate signed URL for playback
$url = Storage::disk('s3')->temporaryUrl($path, now()->addHours(1));
```

### File Name Generation

Use UUID to prevent:

- Path traversal attacks
- Filename collisions
- Predictable file locations

```php
$filename = Str::uuid() . '.m4a';
```

## Related Documentation

- [Requirements Document](./requirements.md) - Feature requirements
- [Design Document](./design.md) - Technical design and architecture
- [Tasks Document](./tasks.md) - Implementation tasks
- [Manual Testing Guide](./manual-testing-guide.md) - Testing procedures

## References

### LINE Messaging API

- [LINE Messaging API Documentation](https://developers.line.biz/en/docs/messaging-api/)
- [Audio Message Format](https://developers.line.biz/en/docs/messaging-api/message-types/#audio-messages)

### AWS S3

- [S3 PutObject API](https://docs.aws.amazon.com/AmazonS3/latest/API/API_PutObject.html)
- [S3 Object Metadata](https://docs.aws.amazon.com/AmazonS3/latest/userguide/UsingMetadata.html)

### Audio Formats

- [M4A Format Specification](https://en.wikipedia.org/wiki/MPEG-4_Part_14)
- [AAC Audio Codec](https://en.wikipedia.org/wiki/Advanced_Audio_Coding)
- [MIME Types for Audio](https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types#audio_and_video_types)

## Changelog

### 2026-02-15

- Added duration field to fish_audios table
- Enhanced audio validation
- Improved error handling

### 2026-02-16

- Fixed Content-Type issue for LINE audio uploads
- Added comprehensive documentation
- Enhanced PHPDoc comments in LineUploadService
- Created implementation guide
