<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Extensions\Registries\MediaUsageRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The shared image library. Uploads are re-encoded to WebP by
 * NewsImageService; images that already sit in the known upload folders
 * (news and page images from before the library existed) are picked up by
 * scan().
 */
class MediaLibrary
{
    /** Folders on the public disk that hold library images. */
    public const DIRECTORIES = ['media', 'news/images', 'pages/images'];

    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

    /** Small copies for the library grid; never listed as library images themselves. */
    public const THUMB_DIRECTORY = 'media/thumbs';

    public function __construct(
        private readonly NewsImageService $images,
        private readonly MediaUsageRegistry $usageRegistry,
    ) {}

    public function upload(UploadedFile $file, ?string $folder, ?User $user): Media
    {
        $path = $this->images->store($file, 'media/'.now()->format('Y/m'));
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'image';

        return $this->record($path, $folder, $user, mb_substr($name, 0, 200));
    }

    /** Add (or return) the library entry for a file already on the public disk. */
    public function record(string $path, ?string $folder = null, ?User $user = null, ?string $name = null): Media
    {
        $disk = Storage::disk('public');
        $size = @getimagesize($disk->path($path)) ?: [null, null];

        return Media::firstOrCreate(['path' => $path], [
            'thumb_path' => $this->thumbnail($path),
            'name' => $name ?? pathinfo($path, PATHINFO_FILENAME),
            'folder' => $folder,
            'mime' => $disk->mimeType($path) ?: 'image/webp',
            'size' => $disk->size($path),
            'width' => $size[0],
            'height' => $size[1],
            'uploaded_by' => $user?->id,
        ]);
    }

    /** Register images on disk that the library does not know yet. Returns how many were added. */
    public function scan(): int
    {
        $disk = Storage::disk('public');
        $known = Media::pluck('path')->flip();
        $added = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (isset($known[$path]) || str_starts_with($path, self::THUMB_DIRECTORY.'/') || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                    continue;
                }

                $this->record($path, match (true) {
                    str_starts_with($path, 'news/') => 'News',
                    str_starts_with($path, 'pages/') => 'Pages',
                    default => null,
                });
                $added++;
            }
        }

        return $added;
    }

    /**
     * Everywhere this image is shown — core content and whatever extensions
     * registered through ExtensionRegistry::mediaUsages().
     *
     * @return array<int, array{type: string, title: string, url: string|null}>
     */
    public function usages(Media $media): array
    {
        return $this->usageRegistry->find($media);
    }

    public function delete(Media $media): void
    {
        Storage::disk('public')->delete(array_filter([$media->path, $media->thumb_path]));
        $media->delete();
    }

    /** A broken or unreadable image still gets a library entry, just without a thumbnail. */
    private function thumbnail(string $path): ?string
    {
        try {
            return $this->images->thumbnail($path, self::THUMB_DIRECTORY.'/'.md5($path).'.webp');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
