<?php

namespace App\Support;

final class YouTubeVideoId
{
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    public static function fromUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $videoId = match (true) {
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
                && ($parts['path'] ?? '') === '/watch' => self::watchVideoId($parts['query'] ?? ''),
            $host === 'youtu.be' => explode('/', trim($parts['path'] ?? '', '/'))[0] ?? null,
            default => null,
        };

        return is_string($videoId) && preg_match(self::ID_PATTERN, $videoId) === 1
            ? $videoId
            : null;
    }

    private static function watchVideoId(string $query): ?string
    {
        parse_str($query, $parameters);

        return isset($parameters['v']) && is_string($parameters['v'])
            ? $parameters['v']
            : null;
    }
}
