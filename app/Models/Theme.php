<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'version', 'author', 'description',
        'type', 'path', 'active', 'preview_image',
        'installed_at', 'activated_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ThemeSetting::class);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function absolutePath(): string
    {
        return base_path('themes/'.$this->path);
    }

    public function previewImageUrl(): ?string
    {
        if (! $this->preview_image) {
            return null;
        }

        return asset('themes/'.$this->path.'/'.$this->preview_image);
    }
}
