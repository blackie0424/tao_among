<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntroCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order'];

    public function slides(): HasMany
    {
        return $this->hasMany(IntroSlide::class, 'category_id');
    }
}
