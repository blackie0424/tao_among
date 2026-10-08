<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_key', 'tao_name', 'notes'];

    protected $hidden = ['name_key'];

    public function captureSessions(): HasMany
    {
        return $this->hasMany(CaptureSession::class);
    }
}
