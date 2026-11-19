<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Extensions\Registries\MediaUsageRegistry;
use App\Services\Media\MediaLibrary;
use App\Services\Media\NewsImageService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-off backfill for images uploaded before every upload was optimised:
 * each library image that is not WebP is re-encoded to WebP, anything wider
 * than NewsImageService::MAX_WIDTH is scaled down, and every page, article,
 * setting and extension column that pointed at the old file is repointed.
 *
 * MOTD banners are not library images and stay as they are: the in-game
 * browser of HL1 games cannot show WebP.
 */
class OptimiseMediaLibrary extends Command
{
    protected $signature = 'hybridcore:media:optimize
        {--dry-run : List what would change without writing anything}
        {--keep : Keep the original files on disk after converting}';

    protected $description = 'Convert library images to WebP, scale down oversized ones and repoint everything that uses them';

    public function handle(MediaLibrary $library, NewsImageService $images, MediaUsageRegistry $usages): int
    {
        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            return $this->preview($disk);
        }

        $added = $library->scan();
        if ($added) {
            $this->line("Added {$added} image(s) the library did not know yet.");
        }

        $todo = Media::all()->filter(fn (Media $m) => $disk->exists($m->path) && (
            ! str_ends_with(strtolower($m->path), '.webp') || ($m->width ?? 0) > NewsImageService::MAX_WIDTH
        ));

        if ($todo->isEmpty()) {
            $this->info('Nothing to do — every library image is already an optimised WebP.');

            return self::SUCCESS;
        }

        $before = 0;
        $after = 0;
        $repointed = 0;

        foreach ($todo as $media) {
            $old = $media->path;
            $size = $disk->size($old);
            $convert = ! str_ends_with(strtolower($old), '.webp');

            // Without Imagick an animation would be frozen into one frame; leave it.
            if ($images->isAnimated($disk->path($old)) && ! extension_loaded('imagick')) {
                $this->line("kept {$old} — animated, and this server has no Imagick to convert it without losing the animation");

                continue;
            }

            if ($dryRun) {
                $this->line(($convert ? 'would convert ' : 'would scale down ').$old.' ('.$this->human($size).')');
                $before += $size;

                continue;
            }

            try {
                $target = $convert ? preg_replace('/\.[^.]+$/', '', $old).'.webp' : $old;
                if ($convert && $disk->exists($target)) {
                    // Never overwrite a different image that already has that name.
                    $target = dirname($old).'/'.Str::uuid().'.webp';
                }
                $new = $images->convertStored($old, $target);
            } catch (Throwable $e) {
                // One unreadable file must not stop the rest.
                $this->error("skipped {$old} — {$e->getMessage()}");

                continue;
            }

            $dimensions = @getimagesize($disk->path($new)) ?: [null, null];
            $media->update([
                'path' => $new,
                'mime' => 'image/webp',
                'size' => $disk->size($new),
                'width' => $dimensions[0],
                'height' => $dimensions[1],
            ]);

            $changed = $new !== $old ? $usages->repoint($old, $new) : 0;
            $repointed += $changed;

            if ($new !== $old && ! $this->option('keep')) {
                $disk->delete($old);
            }

            $before += $size;
            $after += $media->size;
            $this->line(sprintf('%s → %s  %s to %s (-%d%%)%s', basename($old), basename($new), $this->human($size), $this->human($media->size),
                $size > 0 ? max(0, round((1 - $media->size / $size) * 100)) : 0,
                $changed ? ", {$changed} reference(s) repointed" : ''));
        }

        if ($dryRun) {
            $this->info($todo->count().' image(s) would change ('.$this->human($before).' now). Run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($repointed) {
            // References were rewritten with plain queries; cached pages and settings must not keep the old paths.
            Artisan::call('cache:clear');
        }

        $this->info(sprintf('Done: %s → %s, %d reference(s) repointed.', $this->human($before), $this->human($after), $repointed));

        return self::SUCCESS;
    }

    /** Dry run: read the files on disk directly, so images the library has not scanned yet count too. */
    private function preview(Filesystem $disk): int
    {
        $count = 0;
        $bytes = 0;
        foreach (MediaLibrary::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (str_starts_with($path, MediaLibrary::THUMB_DIRECTORY.'/') || ! preg_match('/\.(webp|jpe?g|png|gif)$/i', $path)) {
                    continue;
                }
                $webp = str_ends_with(strtolower($path), '.webp');
                $width = (@getimagesize($disk->path($path)) ?: [0])[0];
                if ($webp && $width <= NewsImageService::MAX_WIDTH) {
                    continue;
                }
                $this->line(($webp ? 'would scale down ' : 'would convert ').$path.' ('.$this->human($disk->size($path)).')');
                $count++;
                $bytes += $disk->size($path);
            }
        }

        $count
            ? $this->info("{$count} image(s) would change ({$this->human($bytes)} now). Run without --dry-run to apply.")
            : $this->info('Nothing to do — every library image is already an optimised WebP.');

        return self::SUCCESS;
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
