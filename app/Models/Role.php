<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'level'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'level' => 'integer'
    ];

    /**
     * Relacja many-to-many z uprawnieniami
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Relacja one-to-many z użytkownikami
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Sprawdź czy rola ma dane uprawnienie
     */
    public function hasPermission($permission)
    {
        if (is_string($permission)) {
            return $this->permissions->contains('name', $permission);
        }
        
        return $this->permissions->contains($permission);
    }

    /**
     * Dodaj uprawnienie do roli
     */
    public function givePermission($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }
        
        if ($permission && !$this->hasPermission($permission)) {
            $this->permissions()->attach($permission);
        }
    }

    /**
     * Usuń uprawnienie z roli
     */
    public function revokePermission($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }
        
        if ($permission) {
            $this->permissions()->detach($permission);
        }
    }
}
