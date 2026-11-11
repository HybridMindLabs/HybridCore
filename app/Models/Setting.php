<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $group
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'group'];

    protected static function getActivityLogEventPrefix(): string
    {
        return 'setting';
    }

    protected static function getActivityLogIdentifier(Model $model): string
    {
        return (string) $model->getAttribute('key');
    }

    /** Stored secrets (OAuth, captcha, SMTP, payment keys) never reach the log. */
    protected static function shouldRedactActivityValues(Model $model): bool
    {
        return (bool) preg_match('/secret|password|token|_key$|api_key|private/i', (string) $model->getAttribute('key'));
    }
}
