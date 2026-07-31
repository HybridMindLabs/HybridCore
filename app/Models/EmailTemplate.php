<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $subject
 * @property string $body_html
 * @property array<array-key, mixed>|null $variables
 * @property bool $active
 * @property bool $system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereBodyHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailTemplate whereVariables($value)
 *
 * @mixin \Eloquent
 */
class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'variables',
        'active',
        'system',
    ];

    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean',
        'system' => 'boolean',
    ];
}
