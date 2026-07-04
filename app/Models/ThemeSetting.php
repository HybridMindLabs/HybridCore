<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
