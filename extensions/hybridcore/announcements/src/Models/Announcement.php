<?php

namespace Hybridcore\Announcements\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $type info | success | warning | danger
 * @property bool $is_active
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property int $sort
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'type', 'is_active', 'starts_at', 'ends_at', 'sort'];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** Active = enabled AND within the optional time window. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
