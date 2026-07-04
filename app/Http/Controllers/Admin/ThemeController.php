<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Themes\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function __construct(private readonly ThemeManager $manager) {}

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
}
