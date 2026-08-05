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
 * @property bool $active
 * @property string|null $preview_image
 * @property Carbon|null $installed_at
 * @property Carbon|null $activated_at
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $license_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ThemeSetting> $settings
 * @property-read int|null $settings_count
 *
 * @method static \Database\Factories\ThemeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereAuthor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereInstalledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePreviewImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereVersion($value)
 *
 * @mixin \Eloquent
 */
class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'version', 'author', 'description',
        'type', 'path', 'active', 'preview_image',
        'installed_at', 'activated_at', 'metadata', 'license_key',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
            'metadata' => 'array',
            // A purchased theme's credential. Encrypted at rest so a database
            // dump or a read-only replica does not hand out access to the
            // author's private repository.
            'license_key' => 'encrypted',
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
