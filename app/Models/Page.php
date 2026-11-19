<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property array<int, array<string, mixed>>|null $blocks
 * @property string $format
 * @property string $layout
 * @property string $status
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_og_image
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static \Database\Factories\PageFactory factory($count = null, $state = [])
 * @method static Builder<static>|Page newModelQuery()
 * @method static Builder<static>|Page newQuery()
 * @method static Builder<static>|Page onlyTrashed()
 * @method static Builder<static>|Page published()
 * @method static Builder<static>|Page query()
 * @method static Builder<static>|Page whereBody($value)
 * @method static Builder<static>|Page whereCreatedAt($value)
 * @method static Builder<static>|Page whereDeletedAt($value)
 * @method static Builder<static>|Page whereFormat($value)
 * @method static Builder<static>|Page whereId($value)
 * @method static Builder<static>|Page whereLayout($value)
 * @method static Builder<static>|Page wherePublishedAt($value)
 * @method static Builder<static>|Page whereSeoDescription($value)
 * @method static Builder<static>|Page whereSeoOgImage($value)
 * @method static Builder<static>|Page whereSeoTitle($value)
 * @method static Builder<static>|Page whereSlug($value)
 * @method static Builder<static>|Page whereStatus($value)
 * @method static Builder<static>|Page whereTitle($value)
 * @method static Builder<static>|Page whereUpdatedAt($value)
 * @method static Builder<static>|Page withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Page withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Page extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'published'];

    public const FORMATS = ['markdown', 'html', 'blocks'];

    /** Revisions kept per page; older ones are pruned on save. */
    public const MAX_REVISIONS = 30;

    public const LAYOUTS = ['default', 'wide', 'centered', 'sidebar'];

    protected $fillable = [
        'title', 'slug', 'body', 'blocks', 'format', 'layout', 'status',
        'seo_title', 'seo_description', 'seo_og_image', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'blocks' => 'array'];
    }

    /** Published and past its publish time — a future published_at schedules the page. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isScheduled(): bool
    {
        return $this->isPublished() && $this->published_at?->isFuture() === true;
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('id');
    }

    /**
     * Store a copy of the page — as it is now, or the given attributes (e.g.
     * getOriginal() before a save) — keeping the newest MAX_REVISIONS copies.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function saveRevision(?User $user, ?array $attributes = null): void
    {
        $source = $attributes ?? $this->getAttributes();
        $blocks = $attributes === null ? $this->blocks : ($attributes['blocks'] ?? null);

        $this->revisions()->create([
            'user_id' => $user?->id,
            'title' => $source['title'],
            'format' => $source['format'] ?? 'markdown',
            'layout' => $source['layout'] ?? 'default',
            'body' => $source['body'] ?? null,
            'blocks' => $blocks,
        ]);

        $keep = $this->revisions()->limit(self::MAX_REVISIONS)->pluck('id');
        $this->revisions()->whereNotIn('id', $keep)->delete();
    }
}
