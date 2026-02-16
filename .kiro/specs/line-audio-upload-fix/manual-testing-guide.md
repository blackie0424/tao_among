# LINE Audio Upload Manual Testing Guide

## Prerequisites

- LINE application installed on mobile device
- Access to LINE Bot (configured webhook)
- Access to web interface
- AWS CLI configured (for S3 metadata checks)

## Testing Steps

### 1. Record and Send Audio via LINE

- [ ] Open LINE application
- [ ] Navigate to the bot conversation
- [ ] Press and hold the microphone button
- [ ] Record a 3-5 second voice message
- [ ] Release to send
- [ ] Note the timestamp of the message

### 2. Verify Bot Response

- [ ] Check that you receive a success message from the bot
- [ ] Expected message format: "音檔已成功儲存" or similar
- [ ] If error message received, note the exact error text

### 3. Check Application Logs

Run the following command to check recent logs:

```bash
tail -f storage/logs/laravel.log | grep -E "(LINE Bot|audio|Audio)"
```

Look for:

- [ ] "LINE Bot audio downloaded" with correct size
- [ ] "LINE Upload: Starting audio upload"
- [ ] "LINE Upload: Audio uploaded successfully"
- [ ] Audio details including duration and file size

### 4. Verify Database Entry

```bash
php artisan tinker
```

Then in tinker:

```php
// Find the most recent fish with audio
$fish = \App\Models\Fish::whereNotNull('audio_filename')
    ->orderBy('updated_at', 'desc')
    ->first();

// Check the audio details
echo "Audio Filename: " . $fish->audio_filename . "\n";
echo "Audio Duration: " . $fish->audio_duration . "ms\n";
echo "Audio URL: " . $fish->audio_url . "\n";
```

Expected:

- [ ] audio_filename is a valid UUID with .m4a extension
- [ ] audio_duration is between 0-5100 milliseconds
- [ ] audio_url is a valid S3 URL

### 5. Check S3 File Metadata

Use the verification script:

```bash
php artisan tinker
```

```php
$fish = \App\Models\Fish::whereNotNull('audio_filename')
    ->orderBy('updated_at', 'desc')
    ->first();

$filename = $fish->audio_filename;
$path = 'audio/' . $filename;

// Get S3 client
$s3 = Storage::disk('s3');

// Check if file exists
if ($s3->exists($path)) {
    echo "✓ File exists in S3\n";

    // Get file size
    $size = $s3->size($path);
    echo "File size: " . $size . " bytes\n";

    // Get URL
    $url = $s3->url($path);
    echo "URL: " . $url . "\n";
} else {
    echo "✗ File does not exist in S3\n";
}
```

Or use AWS CLI directly:

```bash
# Replace with your actual bucket name and file path
aws s3api head-object \
    --bucket YOUR_BUCKET_NAME \
    --key audio/YOUR_AUDIO_FILENAME.m4a
```

Check for:

- [ ] ContentType is "audio/mp4" or "audio/m4a"
- [ ] ContentLength matches expected size
- [ ] File is publicly accessible (if ACL is public-read)

### 6. Test Audio Playback in Web Interface

- [ ] Open the web application
- [ ] Navigate to the fish list
- [ ] Find the fish you just added audio to
- [ ] Click on the fish to view details
- [ ] Locate the audio player
- [ ] Click play button
- [ ] Verify audio plays with sound
- [ ] Verify audio is clear and not corrupted
- [ ] Verify duration matches what was recorded

### 7. Direct S3 URL Test

Copy the audio URL from step 4 and:

- [ ] Open URL directly in browser
- [ ] Verify browser can play the audio
- [ ] Or download the file and play in media player
- [ ] Confirm audio content is correct

## Troubleshooting

### No Sound in Audio

1. Check S3 metadata for correct ContentType
2. Download file directly and check with media player
3. Check browser console for errors
4. Verify file size is reasonable (not 0 or too small)

### Bot Returns Error

1. Check application logs for detailed error
2. Verify LINE webhook is configured correctly
3. Check S3 credentials and permissions
4. Verify fish record exists before sending audio

### File Not Found in S3

1. Check application logs for upload errors
2. Verify S3 bucket name and region
3. Check IAM permissions for S3 upload
4. Verify Storage disk configuration

## Success Criteria

All of the following must be true:

- ✓ Bot responds with success message
- ✓ Database has correct audio_filename and audio_duration
- ✓ File exists in S3 with correct ContentType
- ✓ Audio plays in web interface with clear sound
- ✓ Audio duration matches recorded length
- ✓ No errors in application logs

## Test Results

Date: ******\_\_\_******
Tester: ******\_\_\_******

| Step                | Status        | Notes |
| ------------------- | ------------- | ----- |
| 1. Record & Send    | ☐ Pass ☐ Fail |       |
| 2. Bot Response     | ☐ Pass ☐ Fail |       |
| 3. Application Logs | ☐ Pass ☐ Fail |       |
| 4. Database Entry   | ☐ Pass ☐ Fail |       |
| 5. S3 Metadata      | ☐ Pass ☐ Fail |       |
| 6. Web Playback     | ☐ Pass ☐ Fail |       |
| 7. Direct URL Test  | ☐ Pass ☐ Fail |       |

Overall Result: ☐ Pass ☐ Fail

Additional Notes:

---

---

---
