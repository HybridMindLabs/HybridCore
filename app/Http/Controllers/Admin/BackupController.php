<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ActivityLogService;
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

    public function __construct(private readonly ActivityLogService $activity) {}

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

        // Detect mysqldump availability
        $mysqldumpPath = $this->findMysqldump();

        return Inertia::render('Admin/System/Backup', [
            'counts' => [
                'settings' => Setting::count(),
                'extensions' => Extension::count(),
                'themes' => Theme::count(),
                'pages' => Page::count(),
                'menus' => Menu::count(),
            ],
            'backups' => array_slice($backups, 0, 20),
            'mysqldump_available' => $mysqldumpPath !== null,
        ]);
    }

    /** Run mysqldump and save to storage/app/backups/. */
    public function databaseBackup(): RedirectResponse
    {
        $mysqldump = $this->findMysqldump();
        abort_if($mysqldump === null, 500, 'mysqldump not found on this server.');

        $db = config('database.connections.mysql');
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? 3306;
        $dbname = $db['database'] ?? '';
        $user = $db['username'] ?? 'root';
        $password = $db['password'] ?? '';

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'hybridcore-db-'.now()->format('Y-m-d-His').'.sql';
        $filepath = $dir.'/'.$filename;

        // Write a temporary .cnf to avoid password in the process list
        $cnf = tempnam(sys_get_temp_dir(), 'mysqldump_');
        $cnfData = "[client]\npassword=".addslashes($password)."\n";
        file_put_contents($cnf, $cnfData);
        chmod($cnf, 0600);

        $cmd = sprintf(
            '%s --defaults-extra-file=%s -h %s -P %d -u %s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellcmd($mysqldump),
            escapeshellarg($cnf),
            escapeshellarg($host),
            (int) $port,
            escapeshellarg($user),
            escapeshellarg($dbname),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $exitCode);
        unlink($cnf);

        if ($exitCode !== 0 || ! file_exists($filepath) || filesize($filepath) === 0) {
            @unlink($filepath);

            return back()->withErrors(['db' => 'mysqldump failed. Check server logs.']);
        }

        $this->activity->log('backup.database', 'MySQL database backup created: '.$filename);

        return back()->with('success', 'Database backup created: '.$filename);
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

    private function findMysqldump(): ?string
    {
        // Honour explicit env override
        if ($override = env('MYSQLDUMP_PATH')) {
            return is_executable($override) ? $override : null;
        }

        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/bin/mysqldump'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Last resort: ask the shell
        $found = trim((string) shell_exec('which mysqldump 2>/dev/null'));

        return ($found && is_executable($found)) ? $found : null;
    }

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
