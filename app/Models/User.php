<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'role_id',
        'status',
        'profile_photo',
        'last_login_at',
        'max_discount_percentage',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'max_discount_percentage' => 'decimal:2',
        ];
    }

    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasRole(...$roles)
    {
        $currentRole = strtolower(str_replace(' ', '_', $this->role));
        foreach ($roles as $role) {
            if (strtolower(str_replace(' ', '_', $role)) === $currentRole) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission($permissionKey)
    {
        if ($this->hasRole('super_admin', 'Super Admin')) {
            return true;
        }

        if (!$this->relationLoaded('roleRelation')) {
            $this->load('roleRelation.permissions');
        }

        if (!$this->roleRelation) {
            return false;
        }

        return $this->roleRelation->permissions->contains('name', $permissionKey);
    }
}
