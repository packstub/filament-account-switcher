<?php

namespace Packstub\AccountSwitcher\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasLinkedAccounts;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_admin' => false,
        'can_access_panel' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'can_access_panel' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can_access_panel;
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams()->whereKey($tenant->getKey())->exists();
    }

    public function canImpersonate(self $target): bool
    {
        return $this->is_admin;
    }

    public function canBeImpersonated(self $by): bool
    {
        return ! $this->is_admin;
    }
}
