<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'color', 'icon', 'sort'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')->withPivot('is_primary');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /** Has every permission in the list, or holds the `*` wildcard. */
    public function hasWildcard(): bool
    {
        return $this->permissions->contains('slug', '*');
    }

    /** @return Collection<int, string> */
    public function permissionSlugsCached(): Collection
    {
        return Cache::remember(
            'role.permissions.'.$this->id,
            300,
            fn () => $this->permissions->pluck('slug'),
        );
    }

    public function forgetPermissionCache(): void
    {
        Cache::forget('role.permissions.'.$this->id);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $role) => Cache::forget('role.permissions.'.$role->id));
        static::deleted(fn (self $role) => Cache::forget('role.permissions.'.$role->id));
    }
}
