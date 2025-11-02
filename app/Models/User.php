<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'preferences' => 'array', // JSON preferences
        ];
    }

    /**
     * Relacja z rolą
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Sprawdź czy użytkownik ma daną rolę
     */
    public function hasRole($role)
    {
        // Sprawdź czy role jest załadowane i czy to obiekt
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        if (!$this->role || !is_object($this->role)) {
            return false;
        }
        
        if (is_string($role)) {
            return $this->role->name === $role;
        }
        
        return $this->role->id === $role;
    }

    /**
     * Sprawdź czy użytkownik ma dane uprawnienie
     */
    public function hasPermission($permission)
    {
        // Sprawdź czy role jest załadowane i czy to obiekt
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        return $this->role && is_object($this->role) && method_exists($this->role, 'hasPermission') && $this->role->hasPermission($permission);
    }

    /**
     * Sprawdź czy użytkownik ma poziom uprawnień wyższy lub równy
     */
    public function hasLevel($level)
    {
        // Sprawdź czy role jest załadowane i czy to obiekt
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        return $this->role && is_object($this->role) && isset($this->role->level) && $this->role->level >= $level;
    }

    /**
     * Sprawdź czy użytkownik jest Super Admin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Sprawdź czy użytkownik jest Admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin') || $this->isSuperAdmin();
    }

    /**
     * Sprawdź czy użytkownik jest aktywny
     */
    public function isActive()
    {
        return $this->is_active;
    }
}
