<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
