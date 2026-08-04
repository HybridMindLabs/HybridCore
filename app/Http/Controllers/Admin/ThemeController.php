<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemeSetting;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeManager $manager,
        private readonly ThemeSettingsResolver $themeSettings,
    ) {}

    public function index(): Response
    {
        $themes = Theme::orderByDesc('active')->orderBy('name')->get()->map(fn (Theme $theme) => [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'version' => $theme->version,
            'author' => $theme->author,
            'description' => $theme->description,
            'type' => $theme->type,
            'active' => $theme->active,
            'preview_image_url' => $theme->previewImageUrl(),
            'installed_at' => $theme->installed_at?->toDateString(),
            'activated_at' => $theme->activated_at?->toDateTimeString(),
        ]);

        return Inertia::render('Admin/Themes/Index', [
            'themes' => $themes,
        ]);
    }

    public function show(Theme $theme): Response
    {
        return Inertia::render('Admin/Themes/Show', [
            'theme' => [
                'id' => $theme->id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'version' => $theme->version,
                'author' => $theme->author,
                'description' => $theme->description,
                'type' => $theme->type,
                'path' => $theme->path,
                'active' => $theme->active,
                'preview_image_url' => $theme->previewImageUrl(),
                'metadata' => $theme->metadata,
                'installed_at' => $theme->installed_at?->toDateTimeString(),
                'activated_at' => $theme->activated_at?->toDateTimeString(),
                'settings_schema' => $this->themeSettings->schema($theme),
                'settings' => $this->themeSettings->effective($theme),
                // Presence only — the key itself never leaves the server.
                'has_license' => filled($theme->license_key),
                'requires_license' => (bool) ($theme->metadata['requires_license'] ?? false),
            ],
        ]);
    }

    public function sync(): RedirectResponse
    {
        $count = $this->manager->sync();

        return redirect()->route('admin.themes.index')
            ->with('success', "Sync complete — {$count} theme(s) discovered.");
    }

    public function activate(Theme $theme): RedirectResponse
    {
        $this->manager->activate($theme);

        return redirect()->route('admin.themes.index')
            ->with('success', "{$theme->name} is now the active theme.");
    }

    public function deactivate(Theme $theme): RedirectResponse
    {
        $this->manager->deactivate($theme);

        return redirect()->route('admin.themes.index')
            ->with('success', "{$theme->name} deactivated.");
    }

    /** Build validation rules from the theme's own declared settings_schema. */
    private function settingsValidationRules(Theme $theme): array
    {
        $rules = [];

        foreach ($this->themeSettings->schema($theme) as $field) {
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
     * Save theme setting overrides. Only keys declared in the theme's own
     * settings_schema are validated and persisted — $request->validate()
     * silently drops anything else, so an unknown key never reaches
     * ThemeSetting::updateOrCreate() below.
     */
    public function settings(Theme $theme, Request $request): RedirectResponse
    {
        $data = $request->validate($this->settingsValidationRules($theme));
        $schema = collect($this->themeSettings->schema($theme))->keyBy('key');

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $field = $schema->get($key);

            ThemeSetting::updateOrCreate(
                ['theme_id' => $theme->id, 'key' => $key],
                ['value' => (string) $value, 'type' => $field['type'] === 'toggle' ? 'bool' : 'string'],
            );
        }

        return back()->with('success', "Settings saved for {$theme->name}.");
    }

    /** Save or clear a theme's license key. Mirrors ExtensionController::license(). */
    public function license(Theme $theme, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'license_key' => ['nullable', 'string', 'max:512'],
        ]);

        $key = trim((string) ($data['license_key'] ?? ''));

        $theme->license_key = $key === '' ? null : $key;
        $theme->save();

        if ($key !== '') {
            return back()->with('success', "License saved for {$theme->name}.");
        }

        // Activation is gated on the key, so leaving a now-unlicensed theme
        // serving the public site would make removal meaningless — nothing
        // re-checks the licence on later requests.
        if ($theme->active && ($theme->metadata['requires_license'] ?? false)) {
            $this->manager->deactivate($theme);

            return back()->with('success', "License removed from {$theme->name} — the theme has been deactivated.");
        }

        return back()->with('success', "License removed from {$theme->name}.");
    }
}
