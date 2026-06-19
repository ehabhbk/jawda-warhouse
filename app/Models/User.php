<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'full_name',
        'username',
        'email',
        'password',
        'role',
        'role_id',
        'phone',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStorekeeper(): bool
    {
        return $this->role === 'storekeeper';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'storekeeper_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouses');
    }

    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permissions()
    {
        return $this->hasManyThrough(Permission::class, Role::class, 'id', 'id', 'role_id', 'id')
            ->join('role_permission', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereColumn('role_permission.role_id', 'roles.id');
    }

    public function getPermissionNamesAttribute(): array
    {
        if ($this->isAdmin()) {
            return Permission::pluck('name')->toArray();
        }
        return $this->roleRelation?->permissions?->pluck('name')->toArray() ?? [];
    }
}
