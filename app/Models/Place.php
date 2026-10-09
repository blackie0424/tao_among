<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;

    protected $fillable = ['tribe', 'scope_key', 'name', 'name_key', 'tao_name', 'notes', 'is_provisional'];

    protected $hidden = ['name_key'];

    protected $casts = ['is_provisional' => 'boolean'];

    public function captureSessions(): HasMany
    {
        return $this->hasMany(CaptureSession::class);
    }
}
