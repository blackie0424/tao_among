<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaptureSession extends Model
{
    use HasFactory;

    protected $fillable = ['capture_date', 'tribe', 'capture_method', 'place_id', 'location_hint', 'notes'];

    protected $casts = ['capture_date' => 'date'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function captureRecords(): HasMany
    {
        return $this->hasMany(CaptureRecord::class, 'session_id');
    }
}
