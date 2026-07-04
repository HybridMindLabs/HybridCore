<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class NewsArticle extends Model
{
    use Prunable;
    use Searchable;
    use SoftDeletes;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }

    /** Days a trashed article stays restorable before being purged. */
    public const TRASH_RETENTION_DAYS = 30;

    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::TRASH_RETENTION_DAYS));
    }

    public const STATUSES = ['draft', 'published', 'archived'];

    public const FORMATS = ['markdown', 'html'];

    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'excerpt', 'body', 'format',
        'featured_image', 'status', 'is_pinned', 'is_featured', 'published_at',
        'meta_title', 'meta_description', 'og_image', 'reading_time', 'views',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_featured' => 'boolean',
            'reading_time' => 'integer',
            'views' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            if ($article->isDirty('body')) {
                $words = str_word_count(strip_tags($article->body));
                $article->reading_time = max(1, (int) ceil($words / 200));
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NewsTag::class, 'news_article_tag', 'article_id', 'tag_id');
    }

    public function articleViews(): HasMany
    {
        return $this->hasMany(NewsArticleView::class, 'article_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class, 'article_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->featured_image);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->og_image ?: $this->featured_image);
    }

    /**
     * Uploaded images are stored as a disk-relative path (e.g.
     * "news/images/uuid.png") and resolved to a fresh URL here, so the
     * result always matches the current APP_URL/scheme instead of
     * whatever host happened to be current at upload time (legacy rows may
     * have a full URL baked in from an old dev host). Detection is by path
     * shape, not host: our own uploads always live under "/storage/...",
     * regardless of which host served the request that created them. A
     * genuinely external URL (no "/storage/..." path) is left untouched.
     */
    private function resolveImageUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH) ?? '';

            if (! preg_match('#^/?storage/#', $path)) {
                return $value;
            }

            $value = $path;
        }

        $value = ltrim(preg_replace('#^/?storage/#', '', $value), '/');

        return Storage::disk('public')->url($value);
    }
}
