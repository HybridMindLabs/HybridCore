<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'color', 'icon',
        'meta_title', 'meta_description', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id')
            ->where('status', 'published')
            ->where('published_at', '<=', now());
    }
}
