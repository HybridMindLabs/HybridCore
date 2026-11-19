<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved copy of a page as it was before an edit.
 *
 * @property int $id
 * @property int $page_id
 * @property int|null $user_id
 * @property string $title
 * @property string $format
 * @property string $layout
 * @property string|null $body
 * @property array<int, array<string, mixed>>|null $blocks
 * @property Carbon|null $created_at
 */
class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'user_id', 'title', 'format', 'layout', 'body', 'blocks'];

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
