<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'published'];

    public const FORMATS = ['markdown', 'html'];

    public const LAYOUTS = ['default', 'wide', 'centered', 'sidebar'];

    protected $fillable = [
        'title', 'slug', 'body', 'format', 'layout', 'status',
        'seo_title', 'seo_description', 'seo_og_image', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
