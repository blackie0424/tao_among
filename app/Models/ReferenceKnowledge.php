<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferenceKnowledge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reference_knowledge';

    protected $fillable = [
        'fish_id',
        'reference_id',
        'tribe',
        'content',
        'pages',
        'page_start',
        'page_end',
        'note',
        'created_by',
    ];

    protected $casts = [
        'page_start' => 'integer',
        'page_end' => 'integer',
    ];

    public function fish(): BelongsTo
    {
        return $this->belongsTo(Fish::class, 'fish_id');
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(Reference::class, 'reference_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
