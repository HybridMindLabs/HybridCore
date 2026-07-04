<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    public const REASONS = ['spam', 'abuse', 'off_topic', 'other'];

    /** Report target types exposed to the frontend, mapped to model classes. */
    public const TYPES = [
        'comment' => NewsComment::class,
        'review' => ServerReview::class,
    ];

    protected $fillable = [
        'reporter_id', 'reportable_type', 'reportable_id',
        'reason', 'details', 'status', 'resolved_by',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
