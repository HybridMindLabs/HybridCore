<?php

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LegalPage extends Model
{
    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(HandleInertiaRequests::LEGAL_PAGES_CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'content',
        'content_updated_at',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'content_updated_at' => 'date:Y-m-d',
        'is_system' => 'boolean',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
