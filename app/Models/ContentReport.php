<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reporter_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property string $reason
 * @property string|null $details
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $reportable
 * @property-read User $reporter
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereReportableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereReportableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereReporterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereResolvedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentReport whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
