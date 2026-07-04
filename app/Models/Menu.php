<?php

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'location'];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(HandleInertiaRequests::MENUS_CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort');
    }
}
