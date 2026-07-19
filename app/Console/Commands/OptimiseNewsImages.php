<?php

namespace App\Console\Commands;

use App\Models\NewsArticle;
use App\Services\Media\NewsImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill for images uploaded before NewsImageService existed, which were
 * stored at whatever size they arrived at.
 */
class OptimiseNewsImages extends Command
{
    protected $signature = 'news:optimise-images
                            {--dry-run : Report what would change without writing anything}
                            {--keep : Convert but leave the originals on disk}';

    protected $description = 'Re-encode existing news images to WebP and repoint the articles that use them';

    public function handle(NewsImageService $images): int
    {
        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        $stale = collect($disk->files(NewsImageService::DIRECTORY))
            ->filter(fn (string $path) => ! str_ends_with(strtolower($path), '.webp'));

        if ($stale->isEmpty()) {
            $this->info('Nothing to do — every news image is already WebP.');

            return self::SUCCESS;
        }

        $before = 0;
        $after = 0;

        foreach ($stale as $path) {
            $originalSize = $disk->size($path);
            $before += $originalSize;

            if ($dryRun) {
                $this->line(sprintf('would convert %s (%s)', $path, $this->human($originalSize)));

                continue;
            }

            try {
                $newPath = $images->convertStored($path);
            } catch (\Throwable $e) {
                // One unreadable file must not abandon the rest of the batch.
                $this->error(sprintf('skipped %s — %s', $path, $e->getMessage()));

                continue;
            }

            $newSize = $disk->size($newPath);
            $after += $newSize;

            $rewritten = $this->repoint($path, $newPath);

            // Only once nothing points at it any more. An article body can hold
            // a URL this command never learns about, so --keep exists for the
            // cautious run.
            if (! $this->option('keep')) {
                $disk->delete($path);
            }

            $this->line(sprintf(
                '%s -> %s  %s to %s (-%d%%), %d reference%s repointed',
                basename($path),
                basename($newPath),
                $this->human($originalSize),
                $this->human($newSize),
                $originalSize > 0 ? round((1 - $newSize / $originalSize) * 100) : 0,
                $rewritten,
                $rewritten === 1 ? '' : 's',
            ));
        }

        if ($dryRun) {
            $this->info(sprintf('%d file(s), %s in total. Re-run without --dry-run to convert.', $stale->count(), $this->human($before)));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('%s -> %s across %d file(s).', $this->human($before), $this->human($after), $stale->count()));

        return self::SUCCESS;
    }

    /**
     * Repoint every article field that can hold this filename. The body is
     * matched on the basename rather than a full URL, because the same image
     * may be referenced through an absolute URL, a storage path, or a relative
     * one depending on how it was inserted.
     */
    private function repoint(string $oldPath, string $newPath): int
    {
        $old = basename($oldPath);
        $new = basename($newPath);
        $count = 0;

        NewsArticle::query()
            ->where('featured_image', 'like', '%'.$old.'%')
            ->orWhere('og_image', 'like', '%'.$old.'%')
            ->orWhere('body', 'like', '%'.$old.'%')
            ->each(function (NewsArticle $article) use ($old, $new, &$count) {
                $article->forceFill([
                    'featured_image' => str_replace($old, $new, (string) $article->featured_image) ?: null,
                    'og_image' => str_replace($old, $new, (string) $article->og_image) ?: null,
                    'body' => str_replace($old, $new, (string) $article->body),
                ])->saveQuietly();

                $count++;
            });

        return $count;
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
