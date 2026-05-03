<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'cpf_cnpj',
        'phone',
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
            'role' => UserRole::class,
        ];
    }

    public function isReceptionist(): bool
    {
        return $this->role === UserRole::RECEPTIONIST;
    }

    public function isMechanic(): bool
    {
        return $this->role === UserRole::MECHANIC;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::CLIENT;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $roles = array_map(
            fn ($r) => $r instanceof UserRole ? $r : UserRole::from($r),
            $roles
        );

        return in_array($this->role, $roles);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'client_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'client_id');
    }
}
