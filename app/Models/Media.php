<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An image in the media library, stored on the public disk.
 *
 * @property int $id
 * @property string $path
 * @property string|null $thumb_path
 * @property string $name
 * @property string|null $folder
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url
 * @property-read string $thumb_url
 */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['path', 'thumb_path', 'name', 'folder', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by'];

    protected $appends = ['url', 'thumb_url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : $this->url;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
