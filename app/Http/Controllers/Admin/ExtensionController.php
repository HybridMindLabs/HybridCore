<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\ExtensionSetting;
use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\ExtensionSettingsResolver;
use App\Services\Extensions\ExtensionUpdateService;
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
        private readonly ExtensionUpdateService $updates,
        private readonly ExtensionSettingsResolver $extensionSettings,
    ) {}

    /**
     * Re-poll every extension's declared update feed, bypassing the hourly
     * cache, then fall back into index() so the badges re-render from the
     * fresh result — same round-trip shape as the sync button.
     */
    public function checkUpdates(): RedirectResponse
    {
        $found = count($this->updates->checkAll(fresh: true));

        return redirect()->route('admin.extensions.index')->with(
            'success',
            $found === 0
                ? 'All extensions are up to date.'
                : "{$found} extension(s) have an update available.",
        );
    }

    /**
     * Download and install the available update. The archive goes through the
     * same import pipeline as a hand-uploaded one, so it gets identical
     * manifest, zip-slip and downgrade checks.
     */
    public function update(Extension $extension): RedirectResponse
    {
        try {
            $updated = $this->updates->apply($extension);
        } catch (RuntimeException $e) {
            // Always land back on the list — back() would follow a stale
            // Referer and appear to "jump" to another page.
            return redirect()->route('admin.extensions.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.extensions.index')
            ->with('success', "{$updated->name} updated to {$updated->version}.");
    }

    /**
     * Store (or clear) the license key of a paid extension.
     *
     * The key is never sent back to the browser — the page only ever learns
     * whether one is present. Saving invalidates the cached release for this
     * extension, since a feed that answered 401 a minute ago may answer with a
     * release now.
     */
    public function license(Request $request, Extension $extension): RedirectResponse
    {
        $data = $request->validate([
            'license_key' => ['nullable', 'string', 'max:512'],
        ]);

        $key = trim((string) ($data['license_key'] ?? ''));

        $extension->license_key = $key === '' ? null : $key;
        $extension->save();

        $this->updates->forget($extension);

        return back()->with('success', $key === ''
            ? "License removed from {$extension->name}."
            : "License saved for {$extension->name}.");
    }

    public function index(): Response
    {
        $settingsMap = collect($this->settingsRegistry->compose())->keyBy('slug');

        // Cached (hourly) — the page must not wait on every author's server.
        $available = $this->updates->checkAll();

        $extensions = Extension::orderBy('name')
            ->get()
            ->filter(fn (Extension $ext) => is_dir(base_path('extensions/'.$ext->path)))
            ->map(function (Extension $ext) use ($settingsMap, $available) {
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
                    'update' => $available[$ext->slug] ?? null,
                    'requires_license' => (bool) ($ext->metadata['requires_license'] ?? false),
                    'has_license' => filled($ext->license_key),
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
                // Presence only — the key itself never leaves the server.
                'has_license' => filled($extension->license_key),
                'requires_license' => (bool) ($extension->metadata['requires_license'] ?? false),
                'update_url' => $extension->metadata['update_url'] ?? null,
                'installed_at' => $extension->installed_at?->toDateTimeString(),
                'enabled_at' => $extension->enabled_at?->toDateTimeString(),
                'disabled_at' => $extension->disabled_at?->toDateTimeString(),
                'settings_schema' => $this->extensionSettings->schema($extension),
                'settings' => $this->extensionSettings->effective($extension),
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

    /** Build validation rules from the extension's own declared settings_schema. */
    private function settingsValidationRules(Extension $extension): array
    {
        $rules = [];

        foreach ($this->extensionSettings->schema($extension) as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
                'toggle' => ['nullable', 'boolean'],
                'select' => ['nullable', 'string', 'in:'.implode(',', $field['options'] ?? [])],
                'textarea' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:500'], // text, image
            };
        }

        return $rules;
    }

    /**
     * Save extension setting overrides. Only keys declared in the extension's
     * own settings_schema are validated and persisted — $request->validate()
     * silently drops anything else.
     */
    public function settings(Extension $extension, Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingsValidationRules($extension));
        $schema = collect($this->extensionSettings->schema($extension))->keyBy('key');

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $field = $schema->get($key);

            ExtensionSetting::updateOrCreate(
                ['extension_id' => $extension->id, 'key' => $key],
                ['value' => (string) $value, 'type' => $field['type'] === 'toggle' ? 'bool' : 'string'],
            );
        }

        return back()->with('success', "Settings saved for {$extension->name}.");
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
