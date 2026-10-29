<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role on the roles screen (F3). The seven presets are rows with
 * is_built_in set and a key equal to the Role enum value; a role created
 * on the screen has its own key and a base role, which sets country scope.
 */
class RoleDefinition extends Model
{
    protected $table = 'roles';

    protected $fillable = ['key', 'name_en', 'name_fr', 'name_pt', 'base_role', 'is_built_in'];

    protected function casts(): array
    {
        return ['is_built_in' => 'boolean'];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->{'name_'.$locale} ?: $this->name_en;
    }

    /** Accounts holding this role, built-in or custom. */
    public function userCount(): int
    {
        if ($this->is_built_in) {
            return User::query()->withoutGlobalScopes()->where('role', $this->key)->whereNull('custom_role_id')->count();
        }

        return User::query()->withoutGlobalScopes()->where('custom_role_id', $this->id)->count();
    }

    /** @return array<string, string> permission => grant */
    public function grid(): array
    {
        return $this->permissions()->pluck('grant', 'permission')->all();
    }
}
