<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extension extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'version', 'author', 'description',
        'type', 'path', 'enabled', 'installed_at', 'enabled_at', 'disabled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ExtensionSetting::class);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function absolutePath(): string
    {
        return base_path('extensions/'.$this->path);
    }
}
