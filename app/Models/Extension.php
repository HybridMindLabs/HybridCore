<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $version
 * @property string $author
 * @property string|null $description
 * @property string $type
 * @property string $path
 * @property bool $enabled
 * @property Carbon|null $installed_at
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $license_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ExtensionSetting> $settings
 * @property-read int|null $settings_count
 *
 * @method static \Database\Factories\ExtensionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereAuthor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereDisabledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereEnabledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereInstalledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Extension whereVersion($value)
 *
 * @mixin \Eloquent
 */
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
            // A purchased extension's credential. Encrypted at rest so a
            // database dump or a read-only replica does not hand out access to
            // the author's private repository.
            'license_key' => 'encrypted',
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
