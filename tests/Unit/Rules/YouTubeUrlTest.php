<?php

use App\Rules\YouTubeUrl;
use App\Support\YouTubeVideoId;

function youtubeUrlFailsValidation(mixed $value): bool
{
    $failed = false;
    (new YouTubeUrl)->validate('url', $value, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}

it('extracts video ids from supported YouTube URLs', function (string $url) {
    expect(YouTubeVideoId::fromUrl($url))->toBe('dQw4w9WgXcQ')
        ->and(youtubeUrlFailsValidation($url))->toBeFalse();
})->with([
    'watch URL' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch URL with extra query' => 'https://youtube.com/watch?feature=shared&v=dQw4w9WgXcQ',
    'short URL' => 'https://youtu.be/dQw4w9WgXcQ',
]);

it('rejects unsupported or malformed YouTube URLs', function (mixed $url) {
    expect(is_string($url) ? YouTubeVideoId::fromUrl($url) : null)->toBeNull()
        ->and(youtubeUrlFailsValidation($url))->toBeTrue();
})->with([
    'unrelated host' => 'https://example.com/watch?v=dQw4w9WgXcQ',
    'lookalike host' => 'https://youtube.com.example.org/watch?v=dQw4w9WgXcQ',
    'missing id' => 'https://www.youtube.com/watch',
    'invalid id' => 'https://youtu.be/not-valid',
    'plain text' => 'not a URL',
    'non-string value' => 123,
]);
