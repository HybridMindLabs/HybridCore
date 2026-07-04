<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
