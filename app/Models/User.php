<?php

namespace App\Models;

use App\Domain\Customer\Models\Address;
use App\Domain\Vehicle\Models\CustomerVehicle;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'mobile', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Returns all roles directly assigned to the user. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** Returns vehicles saved in the customer's garage. */
    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    /** Returns saved billing/shipping addresses owned by this customer. */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->latest('id');
    }

    /** Checks a granular permission through currently assigned roles. */
    public function hasPermission(string $permission): bool
    {
        if ($this->roles()->where('slug', 'super-admin')->exists()) {
            return true;
        }

        return $this->roles()->whereHas('permissions', fn ($query) => $query->where('slug', $permission))->exists();
    }

    /** Checks a role slug without exposing permission-storage details to controllers. */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }
}
