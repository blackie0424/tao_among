<?php

namespace App\Models;

use App\Contracts\StorageServiceInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishAudio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fish_audios';

    protected $fillable = ['fish_id', 'name', 'locate', 'spoken_name', 'recorded_by', 'duration'];

    // 錄音者只在田調人員專用的管理頁明確回傳，一般序列化（API、Inertia props）不輸出
    protected $hidden = ['recorded_by'];

    protected $appends = ['url'];

    protected static function booted()
    {
        static::deleting(function ($audio) {
            // 立即刪除 Storage 中的音頻檔案
            // name 欄位儲存檔案名稱（UUID.m4a）
            if ($audio->name) {
                $storage = app(StorageServiceInterface::class);
                $audioFolder = $storage->getAudioFolder();
                $storage->delete($audioFolder.'/'.$audio->name);
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
                if (! isset($attributes['name']) || $attributes['name'] === null) {
                    return null;
                }

                return app(StorageServiceInterface::class)->getUrl('audios', $attributes['name'], null);
            }
        );
    }
}
