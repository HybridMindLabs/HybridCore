<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $extension_id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Extension $extension
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereExtensionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtensionSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
class ExtensionSetting extends Model
{
    protected $fillable = ['extension_id', 'key', 'value', 'type'];

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
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
