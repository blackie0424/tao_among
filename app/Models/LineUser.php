<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_user_id',
        'display_name',
        'picture_url',
        'role',
    ];

    public function isEditor(): bool
    {
        return in_array($this->role, ['editor', 'admin']);
    }
}
