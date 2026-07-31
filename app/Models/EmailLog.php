<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $to
 * @property string $subject
 * @property string|null $template_slug
 * @property string $status
 * @property string|null $error
 * @property int|null $user_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Database\Factories\EmailLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereTemplateSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'to',
        'subject',
        'template_slug',
        'status',
        'error',
        'user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
