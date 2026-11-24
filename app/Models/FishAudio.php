<?php

namespace App\Models;

use App\Contracts\StorageServiceInterface;
use App\Models\Builders\FishAudioBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishAudio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fish_audios';

    /** 錄音最長毫秒數（網頁與 LINE 共用，伺服器以送出的長度檢查） */
    public const MAX_DURATION_MS = 5000;

    /** 錄音檔大小上限（位元組）：2 MB */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** 網頁錄音檔可用的副檔名 */
    public const EXTENSIONS = ['mp3', 'wav', 'm4a', 'aac', 'webm'];

    /** 暫存簽名網址的有效秒數 */
    public const UPLOAD_TTL_SECONDS = 300;

    protected $fillable = ['fish_id', 'name', 'locate', 'spoken_name', 'is_primary', 'recorded_by', 'duration'];

    // 錄音者只在田調人員專用的管理頁明確回傳，一般序列化（API、Inertia props）不輸出
    protected $hidden = ['recorded_by', 'revision'];

    protected $casts = ['is_primary' => 'boolean'];

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

    /** 登入者專屬的暫存路徑（bucket 的生命週期規則會自動清掉暫存檔，做法同 #157）。 */
    public static function pendingPath(int $userId, string $filename): string
    {
        return config('storage.drivers.s3.folders.response_audio_pending').'/'.$userId.'/'.$filename;
    }

    /** 暫存檔名的格式：UUID 加白名單副檔名，不含任何路徑。 */
    public static function filenamePattern(): string
    {
        return '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.('.implode('|', self::EXTENSIONS).')$/';
    }

    public function newEloquentBuilder($query): FishAudioBuilder
    {
        return new FishAudioBuilder($query);
    }

    /** 錄音者（舊資料與 LINE 查無帳號時為空） */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
