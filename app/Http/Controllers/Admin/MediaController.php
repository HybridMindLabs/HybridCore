<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\ActivityLogService;
use App\Services\Media\MediaLibrary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaLibrary $library,
        private readonly ActivityLogService $activity,
    ) {}

    /** The page is a shell; the browser component loads the listing from browse(). */
    public function index(): Response
    {
        return Inertia::render('Admin/Media/Index');
    }

    /** Library listing for the media page and the picker modal. */
    public function browse(Request $request): JsonResponse
    {
        return response()->json(['media' => $this->listing($request), 'folders' => $this->folders()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
            'folder' => ['nullable', 'string', 'max:80'],
        ]);

        $folder = $this->folderName($request->input('folder'));
        $uploaded = collect($request->file('files'))
            ->map(fn ($file) => $this->library->upload($file, $folder, $request->user()));

        $this->activity->log('media.uploaded', 'Uploaded '.$uploaded->count().' image(s) to the media library');

        return response()->json(['media' => $uploaded->map(fn (Media $m) => $this->present($m))->values()]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'alt' => ['nullable', 'string', 'max:255'],
            'folder' => ['nullable', 'string', 'max:80'],
        ]);
        $data['folder'] = $this->folderName($data['folder'] ?? null);

        $media->update($data);

        return back()->with('success', 'Image updated.');
    }

    public function usages(Media $media): JsonResponse
    {
        return response()->json(['usages' => $this->library->usages($media)]);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $usages = $this->library->usages($media);
        if ($usages !== [] && ! $request->boolean('force')) {
            return back()->with('error', 'This image is still used by '.count($usages).' page(s) or article(s).');
        }

        $this->library->delete($media);
        $this->activity->log('media.deleted', "Deleted image \"{$media->name}\" from the media library");

        return back()->with('success', 'Image deleted.');
    }

    /**
     * Delete several images. Images still in use are skipped and listed back,
     * so the admin can look at them and confirm with force.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'force' => ['boolean'],
        ]);

        $deleted = 0;
        $inUse = [];
        foreach (Media::whereKey($data['ids'])->get() as $media) {
            $usages = $this->library->usages($media);
            if ($usages !== [] && ! ($data['force'] ?? false)) {
                $inUse[] = ['id' => $media->id, 'name' => $media->name, 'usages' => count($usages)];

                continue;
            }

            $this->library->delete($media);
            $deleted++;
        }

        if ($deleted) {
            $this->activity->log('media.deleted', "Deleted {$deleted} image(s) from the media library");
        }

        return response()->json(['deleted' => $deleted, 'in_use' => $inUse]);
    }

    public function scan(): RedirectResponse
    {
        $added = $this->library->scan();

        return back()->with('success', $added ? "Found {$added} image(s) that were not in the library yet." : 'The library is up to date.');
    }

    private function listing(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $folder = (string) $request->query('folder', '');
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Media::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('alt', 'like', $like)))
            ->when($folder === '__none', fn ($q) => $q->whereNull('folder'))
            ->when($folder !== '' && $folder !== '__none', fn ($q) => $q->where('folder', $folder))
            ->latest('id')
            ->paginate(48)
            ->withQueryString()
            ->through(fn (Media $m) => $this->present($m));
    }

    /** @return array<int, string> */
    private function folders(): array
    {
        return Media::whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder')->all();
    }

    private function folderName(mixed $folder): ?string
    {
        $folder = trim(preg_replace('/\s+/', ' ', (string) $folder));

        return $folder === '' ? null : mb_substr($folder, 0, 80);
    }

    /** @return array<string, mixed> */
    private function present(Media $m): array
    {
        return [
            'id' => $m->id,
            'url' => $m->url,
            'thumb' => $m->thumb_url,
            'name' => $m->name,
            'folder' => $m->folder,
            'alt' => $m->alt,
            'size' => $m->size,
            'width' => $m->width,
            'height' => $m->height,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
