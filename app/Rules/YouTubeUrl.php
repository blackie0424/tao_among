<?php

namespace App\Rules;

use App\Support\YouTubeVideoId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || YouTubeVideoId::fromUrl($value) === null) {
            $fail('請輸入有效的 YouTube 影片連結。');
        }
    }
}
