<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * News images were stored exactly as uploaded. A camera-sized PNG dropped into
 * an article stayed a camera-sized PNG: two of them on the news index came to
 * 2.9 MB between them and pushed LCP past 14s on a throttled mobile connection.
 *
 * Every upload is now capped in width and re-encoded to WebP, which is what
 * avatars and banners have always done.
 *
 * Animated GIFs and WebPs keep their animation: with the Imagick extension
 * they become animated WebP; without it (GD reads only the first frame) the
 * original file is kept as it is rather than frozen.
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
    public function store(UploadedFile $file, string $directory = self::DIRECTORY): string
    {
        $source = $file->getRealPath();

        if ($this->isAnimated($source) && ! $this->canAnimate()) {
            $path = $directory.'/'.Str::uuid().'.'.(self::isGif($source) ? 'gif' : 'webp');
            Storage::disk('public')->put($path, (string) file_get_contents($source));

            return $path;
        }

        $path = $directory.'/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $this->encode($source));

        return $path;
    }

    /** True for a GIF or WebP with more than one frame. */
    public function isAnimated(string $absolutePath): bool
    {
        $bytes = (string) @file_get_contents($absolutePath, false, null, 0, 5 * 1024 * 1024);

        if (str_starts_with($bytes, 'GIF8')) {
            // Every frame starts with a graphic control extension block.
            return preg_match_all('/\x00\x21\xF9\x04/', $bytes) > 1;
        }

        return str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' && str_contains(substr($bytes, 12, 64), 'ANIM');
    }

    /** Overridable so tests can exercise the path without Imagick. */
    protected function canAnimate(): bool
    {
        return extension_loaded('imagick');
    }

    private static function isGif(string $absolutePath): bool
    {
        return (string) @file_get_contents($absolutePath, false, null, 0, 4) === 'GIF8';
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

    /**
     * Write a small WebP copy of an image already on the public disk, for
     * grids and pickers that would otherwise download the full image.
     */
    public function thumbnail(string $path, string $target, int $width = 480): string
    {
        $disk = Storage::disk('public');
        $disk->put($target, $this->encode($disk->path($path), $width));

        return $target;
    }

    private function encode(string $absolutePath, int $maxWidth = self::MAX_WIDTH): string
    {
        // Only Imagick keeps every frame of an animation; GD stays the default
        // because it is faster and always present.
        $driver = $this->canAnimate() && $this->isAnimated($absolutePath) ? new ImagickDriver : new GdDriver;
        $image = (new ImageManager($driver))->decodePath($absolutePath);

        // scaleDown rather than scale: it never enlarges, so an image that is
        // already small is re-encoded but not stretched.
        $image->scaleDown(width: $maxWidth);

        // Encoding drops EXIF along the way, which also takes any GPS
        // coordinates the uploader did not know were in the file.
        return (string) $image->encode(new WebpEncoder(quality: self::QUALITY));
    }
}
