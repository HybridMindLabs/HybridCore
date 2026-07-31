<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $theme_id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Theme $theme
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereThemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ThemeSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
class ThemeSetting extends Model
{
    protected $fillable = ['theme_id', 'key', 'value', 'type'];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'bool' => (bool) $this->value,
            'int' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
