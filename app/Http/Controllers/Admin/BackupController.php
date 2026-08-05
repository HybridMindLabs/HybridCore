<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backup/export controller.
 * JSON metadata exports + real mysqldump database backups.
 */
class BackupController extends Controller
{
    private const SENSITIVE_SETTING_PATTERNS = ['password', 'secret', 'token', 'key', 'api'];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly DatabaseBackupService $dbBackup,
        private readonly SettingsService $settings,
    ) {}

    public function index(): Response
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (is_dir($backupDir)) {
            foreach (scandir($backupDir, SCANDIR_SORT_DESCENDING) as $file) {
                if (str_ends_with($file, '.json') || str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz')) {
                    $path = $backupDir.'/'.$file;
                    $ext = str_ends_with($file, '.sql.gz') ? 'sql.gz' : pathinfo($file, PATHINFO_EXTENSION);
                    $backups[] = [
                        'filename' => $file,
                        'type' => $ext === 'json' ? 'json' : 'sql',
                        'size_kb' => round(filesize($path) / 1024, 1),
                        'created_at' => date('Y-m-d H:i', filemtime($path)),
                    ];
                }
            }
        }

        return Inertia::render('Admin/System/Backup', [
            'counts' => [
                'settings' => Setting::count(),
                'extensions' => Extension::count(),
                'themes' => Theme::count(),
                'pages' => Page::count(),
                'menus' => Menu::count(),
            ],
            'backups' => array_slice($backups, 0, 20),
            'mysqldump_available' => $this->dbBackup->findMysqldump() !== null,
            'schedule' => [
                'backup_schedule' => $this->settings->get('backup_schedule', 'off'),
                'backup_time' => $this->settings->get('backup_time', '03:00'),
                'backup_retention' => (int) $this->settings->get('backup_retention', 7),
                'last_run_at' => $this->settings->get('backup_last_run_at'),
            ],
        ]);
    }

    /** Run mysqldump now and save to storage/app/backups/. */
    public function databaseBackup(): RedirectResponse
    {
        $result = $this->dbBackup->create();

        if (! $result['ok']) {
            return back()->withErrors(['db' => $result['error']]);
        }

        $this->activity->log('backup.database', 'MySQL database backup created: '.$result['filename']);

        return back()->with('success', 'Database backup created: '.$result['filename']);
    }

    /** Update the automatic backup schedule (off/daily/weekly/monthly). */
    public function updateSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_schedule' => ['required', 'in:off,daily,weekly,monthly'],
            'backup_time' => ['required', 'date_format:H:i'],
            'backup_retention' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $this->settings->setMany($data);

        $this->activity->log(
            'backup.schedule-updated',
            "Backup schedule set to {$data['backup_schedule']} at {$data['backup_time']}, keeping {$data['backup_retention']} backups"
        );

        return back()->with('success', 'Backup schedule updated.');
    }

    /** Download a specific stored backup file. */
    public function downloadBackup(string $filename): BinaryFileResponse
    {
        $path = storage_path('app/backups/'.basename($filename));
        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    /** Delete a stored backup file. */
    public function deleteBackup(string $filename): RedirectResponse
    {
        $path = storage_path('app/backups/'.basename($filename));
        if (file_exists($path)) {
            unlink($path);
        }

        return back()->with('success', 'Backup deleted.');
    }

    /** Generate a full backup — saves to storage and downloads. */
    public function generateBackup(): JsonResponse
    {
        $data = $this->buildFullBackup();

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'hybridcore-backup-'.now()->format('Y-m-d-His').'.json';
        file_put_contents($dir.'/'.$filename, json_encode($data, JSON_PRETTY_PRINT));

        $this->activity->log('backup.generated', 'Generated full platform backup: '.$filename);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT);
    }

    /** Import a backup JSON file and restore settings + content. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json', 'max:10240'],
        ]);

        $json = file_get_contents($request->file('backup_file')->getPathname());
        $data = json_decode($json, true);

        abort_if($data === null || ! isset($data['type']) || $data['type'] !== 'full', 422, 'Invalid backup file.');

        $restored = [];

        // Restore settings (skip sensitive)
        if (! empty($data['data']['settings']) && is_array($data['data']['settings'])) {
            foreach ($data['data']['settings'] as $key => $value) {
                if (! $this->isSensitiveKey((string) $key)) {
                    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
                }
            }
            $restored[] = 'settings';
        }

        // Restore pages
        if (! empty($data['data']['content']['pages']) && is_array($data['data']['content']['pages'])) {
            foreach ($data['data']['content']['pages'] as $page) {
                if (empty($page['slug'])) {
                    continue;
                }
                Page::updateOrCreate(
                    ['slug' => $page['slug']],
                    collect($page)->only(['title', 'body', 'status', 'seo_title', 'seo_description', 'published_at'])->toArray()
                );
            }
            $restored[] = 'pages';
        }

        // Restore menus
        if (! empty($data['data']['content']['menus']) && is_array($data['data']['content']['menus'])) {
            foreach ($data['data']['content']['menus'] as $menuData) {
                if (empty($menuData['slug'])) {
                    continue;
                }
                $menu = Menu::updateOrCreate(
                    ['slug' => $menuData['slug']],
                    collect($menuData)->only(['name', 'location'])->toArray()
                );
                if (! empty($menuData['items']) && is_array($menuData['items'])) {
                    $menu->items()->delete();
                    foreach ($menuData['items'] as $item) {
                        $menu->items()->create($item);
                    }
                }
            }
            $restored[] = 'menus';
        }

        $this->activity->log('backup.restored', 'Restored backup: '.implode(', ', $restored));

        return back()->with('success', 'Backup restored: '.implode(', ', $restored).'.');
    }

    // --- Individual section exports ---

    public function exportSettings(): JsonResponse
    {
        $settings = Setting::all()
            ->reject(fn (Setting $s) => $this->isSensitiveKey($s->key))
            ->pluck('value', 'key');

        $this->activity->log('backup.settings-exported', 'Exported settings (secrets excluded)');

        return $this->download('settings', $settings->toArray());
    }

    public function exportExtensions(): JsonResponse
    {
        $extensions = Extension::all(['name', 'slug', 'version', 'author', 'type', 'enabled', 'path']);
        $this->activity->log('backup.extensions-exported', 'Exported extension list');

        return $this->download('extensions', $extensions->toArray());
    }

    public function exportThemes(): JsonResponse
    {
        $themes = Theme::all(['name', 'slug', 'version', 'author', 'type', 'active']);
        $this->activity->log('backup.themes-exported', 'Exported theme list');

        return $this->download('themes', $themes->toArray());
    }

    public function exportContent(): JsonResponse
    {
        $data = [
            'pages' => Page::all(['title', 'slug', 'body', 'status', 'seo_title', 'seo_description', 'published_at'])->toArray(),
            'menus' => Menu::with('items')->get()->map(fn (Menu $m) => [
                'name' => $m->name,
                'slug' => $m->slug,
                'location' => $m->location,
                'items' => $m->items->map->only(['label', 'url', 'target', 'sort'])->all(),
            ])->toArray(),
        ];

        $this->activity->log('backup.content-exported', 'Exported pages and menus');

        return $this->download('content', $data);
    }

    // --- Helpers ---

    private function buildFullBackup(): array
    {
        $settings = Setting::all()
            ->reject(fn (Setting $s) => $this->isSensitiveKey($s->key))
            ->pluck('value', 'key')
            ->toArray();

        return [
            'exported_at' => now()->toIso8601String(),
            'type' => 'full',
            'version' => config('app.version', '1.0.0'),
            'data' => [
                'settings' => $settings,
                'extensions' => Extension::all(['name', 'slug', 'version', 'author', 'type', 'enabled', 'path'])->toArray(),
                'themes' => Theme::all(['name', 'slug', 'version', 'author', 'type', 'active'])->toArray(),
                'content' => [
                    'pages' => Page::all(['title', 'slug', 'body', 'status', 'seo_title', 'seo_description', 'published_at'])->toArray(),
                    'menus' => Menu::with('items')->get()->map(fn (Menu $m) => [
                        'name' => $m->name,
                        'slug' => $m->slug,
                        'location' => $m->location,
                        'items' => $m->items->map->only(['label', 'url', 'target', 'sort'])->all(),
                    ])->toArray(),
                ],
            ],
        ];
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_SETTING_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<mixed> $data */
    private function download(string $name, array $data): JsonResponse
    {
        $filename = 'hybridcore-'.$name.'-'.now()->format('Y-m-d-His').'.json';

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'type' => $name,
            'data' => $data,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT);
    }
}
