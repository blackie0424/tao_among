<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class CaptureDate
{
    public static function today(): string
    {
        return Carbon::now(config('fish_options.capture_timezone'))->toDateString();
    }
}
