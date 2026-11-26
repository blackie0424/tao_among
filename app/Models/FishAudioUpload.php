<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 已消耗的網頁錄音暫存檔（見 AudioConfirmService）。 */
class FishAudioUpload extends Model
{
    protected $fillable = ['upload_key', 'fish_audio_id'];

    public static function keyFor(int $userId, string $pendingFilename): string
    {
        return $userId.':'.$pendingFilename;
    }
}
