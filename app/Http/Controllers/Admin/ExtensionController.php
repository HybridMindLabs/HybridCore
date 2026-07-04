<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\Registries\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly ExtensionManager $manager,
        private readonly SettingsRegistry $settingsRegistry,
    ) {}

    public function index(): Response
    {
        $settingsMap = collect($this->settingsRegistry->compose())->keyBy('slug');

        $extensions = Extension::orderBy('name')
            ->get()
            ->filter(fn (Extension $ext) => is_dir(base_path('extensions/'.$ext->path)))
            ->map(function (Extension $ext) use ($settingsMap) {
                $settingsSlug = str($ext->path)->afterLast('/')->toString();
                $settingsPage = $settingsMap->get($settingsSlug);

                return [
                    'id' => $ext->id,
                    'name' => $ext->name,
                    'slug' => $ext->slug,
                    'version' => $ext->version,
                    'author' => $ext->author,
                    'description' => $ext->description,
                    'type' => $ext->type,
                    'enabled' => $ext->enabled,
                    'settings_url' => $settingsPage ? $settingsPage['url'] : null,
                    'installed_at' => $ext->installed_at?->toDateString(),
                    'enabled_at' => $ext->enabled_at?->toDateTimeString(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Extensions/Index', [
            'extensions' => $extensions,
            'rebuild' => [
                'status' => $this->manager->rebuildStatus(),
                'last_at' => $this->manager->lastRebuildAt(),
            ],
        ]);
    }

    public function show(Extension $extension): Response
    {
        return Inertia::render('Admin/Extensions/Show', [
            'extension' => [
                'id' => $extension->id,
                'name' => $extension->name,
                'slug' => $extension->slug,
                'version' => $extension->version,
                'author' => $extension->author,
                'description' => $extension->description,
                'type' => $extension->type,
                'path' => $extension->path,
                'enabled' => $extension->enabled,
                'metadata' => $extension->metadata,
                'installed_at' => $extension->installed_at?->toDateTimeString(),
                'enabled_at' => $extension->enabled_at?->toDateTimeString(),
                'disabled_at' => $extension->disabled_at?->toDateTimeString(),
            ],
            'rebuild' => [
                'status' => $this->manager->rebuildStatus(),
                'last_at' => $this->manager->lastRebuildAt(),
            ],
        ]);
    }

    public function sync(): RedirectResponse
    {
        $count = $this->manager->sync();

        return redirect()->route('admin.extensions.index')
            ->with('success', "Sync complete — {$count} extension(s) discovered.");
    }

    public function rebuild(): RedirectResponse
    {
        $this->manager->dispatchRebuild();

        return redirect()->route('admin.extensions.index')
            ->with('success', 'Asset rebuild queued. This may take a minute.');
    }

    /**
     * Step 1 of the ZIP import flow: parse and validate the manifest, stash
     * the archive, and hand the admin a preview to confirm before anything
     * is actually extracted onto disk.
     */
    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'archive' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        try {
            $preview = $this->manager->previewZip($request->file('archive'));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($preview);
    }

    /** Step 2: extract the previously previewed archive and sync it in. */
    public function confirmImport(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'uuid']]);

        try {
            $extension = $this->manager->confirmImport($data['token']);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.extensions.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.extensions.show', $extension)
            ->with('success', "{$extension->name} imported. Review it and enable when ready.");
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:enable,disable'],
            'extension_ids' => ['required', 'array', 'min:1'],
            'extension_ids.*' => ['integer', 'exists:extensions,id'],
        ]);

        $extensions = Extension::whereIn('id', $data['extension_ids'])->get();

        foreach ($extensions as $extension) {
            $data['action'] === 'enable' ? $this->manager->enable($extension) : $this->manager->disable($extension);
        }

        return redirect()->route('admin.extensions.index')
            ->with('success', ucfirst($data['action']).'d '.count($extensions).' extension(s).');
    }

    public function enable(Extension $extension): RedirectResponse
    {
        try {
            $this->manager->enable($extension);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.extensions.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.extensions.index')
            ->with('success', "{$extension->name} enabled. Assets rebuilding in background.");
    }

    public function uninstall(Request $request, Extension $extension): RedirectResponse
    {
        $dropData = $request->boolean('drop_data', true);
        $name = $extension->name;

        $this->manager->uninstall($extension, $dropData);

        return redirect()->route('admin.extensions.index')
            ->with('success', "{$name} uninstalled".($dropData ? ' and its data removed.' : ' (data kept).'));
    }

    public function disable(Extension $extension): RedirectResponse
    {
        $this->manager->disable($extension);

        return redirect()->route('admin.extensions.index')
            ->with('success', "{$extension->name} disabled. Assets rebuilding in background.");
    }
}
