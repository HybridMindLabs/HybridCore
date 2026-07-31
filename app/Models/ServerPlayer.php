<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $snapshot_id
 * @property string|null $name
 * @property int $score
 * @property int $duration
 * @property-read string $duration_formatted
 * @property-read ServerSnapshot $snapshot
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerPlayer whereSnapshotId($value)
 *
 * @mixin \Eloquent
 */
class ServerPlayer extends Model
{
    public $timestamps = false;

    protected $fillable = ['snapshot_id', 'name', 'score', 'duration'];

    protected $casts = [
        'score' => 'integer',
        'duration' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ServerSnapshot::class, 'snapshot_id');
    }

    public function getDurationFormattedAttribute(): string
    {
        $h = intdiv($this->duration, 3600);
        $m = intdiv($this->duration % 3600, 60);

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
