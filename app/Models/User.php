<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Clé primaire UUID (pas auto-increment)
     */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'username',
        'full_name',
        'full_name_ar',
        'password',
        'google_id',
        'google_token',
        'google_avatar',
        'auth_provider',
        'avatar_url',
        'bio',
        'profession',
        'role',
        'status',
        'karma_points',
        'is_verified_jurist',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'karma_points'      => 'integer',
            'is_verified_jurist' => 'boolean',
        ];
    }

    // ── Relations ────────────────────────────────────────

    public function commentaries()
    {
        return $this->hasMany(Commentary::class, 'author_id');
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Helpers ──────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    public function isContributor(): bool
    {
        return in_array($this->role, ['admin', 'moderator', 'contributor']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
