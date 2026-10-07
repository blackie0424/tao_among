<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'source',
        'line_user_id',
        'picture_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['editor', 'admin']);
    }

    public function canBrowse(): bool
    {
        return $this->role !== 'guest';
    }

    public function canAccessAudio(): bool
    {
        return self::roleCanAccessAudio($this->role);
    }

    public static function roleCanAccessAudio(?string $role): bool
    {
        return in_array($role, ['editor', 'admin'], true);
    }

    public function canAccessLocation(): bool
    {
        return self::roleCanAccessLocation($this->role);
    }

    public static function roleCanAccessLocation(?string $role): bool
    {
        return in_array($role, ['editor', 'admin'], true);
    }
}
