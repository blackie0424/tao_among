<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Contracts\StorageServiceInterface;

class FishAudio extends Model
{

    use HasFactory, SoftDeletes;
    protected $table = 'fish_audios';

    protected $fillable = ['fish_id', 'name', 'locate', 'duration'];

    protected $appends = ['url'];

    protected static function booted()
    {
        static::deleting(function ($audio) {
            // 立即刪除 Storage 中的音頻檔案
            // name 欄位儲存檔案名稱（UUID.m4a）
            if ($audio->name) {
                $storage = app(StorageServiceInterface::class);
                $audioFolder = $storage->getAudioFolder();
                $storage->delete($audioFolder . '/' . $audio->name);
            }
        });
    }

    // 多對一關聯：一筆筆記屬於一隻魚
    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }

    /**
     * 取得音檔的完整 URL
     * 呼叫方式: $audio->url
     *
     * name 欄位儲存檔案名稱（UUID.m4a）
     * locate 欄位儲存地區名稱（iraraley、imorod 等）
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // 檢查 name 是否存在且不為 null
                // name 欄位儲存檔案名稱
                if (!isset($attributes['name']) || $attributes['name'] === null) {
                    return null;
                }
                
                return app(StorageServiceInterface::class)->getUrl('audios', $attributes['name'], null);
            }
        );
    }
}
