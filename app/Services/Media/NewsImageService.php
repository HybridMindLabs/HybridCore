<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * News images were stored exactly as uploaded. A camera-sized PNG dropped into
 * an article stayed a camera-sized PNG: two of them on the news index came to
 * 2.9 MB between them and pushed LCP past 14s on a throttled mobile connection.
 *
 * Every upload is now capped in width and re-encoded to WebP, which is what
 * avatars and banners have always done.
 */
class NewsImageService
{
    /**
     * Article images render full-bleed, so this has to cover a desktop hero
     * without being a source file. 1920 is a 1x desktop hero and roughly 4x a
     * phone's CSS width — beyond that the extra pixels are never resolved.
     */
    public const MAX_WIDTH = 1920;

    public const QUALITY = 82;

    public const DIRECTORY = 'news/images';

    /** Re-encode an upload and return its storage path. */
    public function store(UploadedFile $file): string
    {
        $path = self::DIRECTORY.'/'.Str::uuid().'.webp';

        Storage::disk('public')->put($path, $this->encode($file->getRealPath()));

        return $path;
    }

    /**
     * Re-encode an image already on the public disk to WebP beside itself, and
     * return the new path. The caller owns deciding what happens to the
     * original — references to it may still be sitting in article bodies.
     */
    public function convertStored(string $path): string
    {
        $disk = Storage::disk('public');
        $target = preg_replace('/\.[^.]+$/', '', $path).'.webp';

        $disk->put($target, $this->encode($disk->path($path)));

        return $target;
    }

    private function encode(string $absolutePath): string
    {
        $image = (new ImageManager(new Driver))->read($absolutePath);

        // scaleDown rather than scale: it never enlarges, so an image that is
        // already small is re-encoded but not stretched.
        $image->scaleDown(width: self::MAX_WIDTH);

        // Encoding drops EXIF along the way, which also takes any GPS
        // coordinates the uploader did not know were in the file.
        return (string) $image->toWebp(self::QUALITY);
    }
}
