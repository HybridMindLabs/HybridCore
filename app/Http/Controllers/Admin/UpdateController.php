<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public const VERSION = '0.3.0';

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {
        //
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Update/Index', [
            'currentCommit' => $this->currentCommit(),
            'currentBranch' => $this->currentBranch(),
            'version' => self::VERSION,
            'channel' => $this->settings->get('update_channel', 'stable'),
            'isGitInstall' => is_dir(base_path('.git')),
            'panelUpdatesEnabled' => (bool) config('hybridcore.panel_updates'),
            'latestRelease' => fn () => app(UpdateService::class)->latestRelease(),
        ]);
    }

    public function updateChannel(Request $request): RedirectResponse
    {
        $request->validate([
            'channel' => ['required', 'in:stable,beta,dev'],
        ]);

        $this->settings->set('update_channel', $request->input('channel'));

        $this->activity->log('settings.updated', 'Update channel changed to '.$request->input('channel'));

        return back()->with('success', 'Update channel saved.');
    }

    public function check(): JsonResponse
    {
        shell_exec('cd '.base_path().' && git fetch origin 2>&1');
        $log = shell_exec('cd '.base_path().' && git log HEAD..origin/$(git rev-parse --abbrev-ref HEAD) --pretty=format:\'%H|%s|%an|%ar\' 2>&1');

        $commits = [];
        if ($log) {
            foreach (array_filter(explode("\n", trim($log))) as $line) {
                [$hash, $subject, $author, $date] = array_pad(explode('|', $line, 4), 4, '');
                $commits[] = compact('hash', 'subject', 'author', 'date');
            }
        }

        return response()->json([
            'commits' => $commits,
            'upToDate' => empty($commits),
        ]);
    }

    public function apply(): JsonResponse
    {
        abort_unless((bool) config('hybridcore.panel_updates'), 403, 'Panel updates are disabled on this installation.');
        abort_unless(is_dir(base_path('.git')), 422, 'Not a git installation — update from the CLI: php artisan hybridcore:update --local');

        $log = [];

        // The whole sequence runs behind maintenance mode; it is always
        // lifted again even when a step fails midway.
        Artisan::call('down', ['--retry' => 30]);
        $log[] = ['step' => 'maintenance on', 'output' => ''];

        try {
            $pull = shell_exec('cd '.base_path().' && git pull --ff-only 2>&1');
            $log[] = ['step' => 'git pull', 'output' => trim($pull ?? '')];

            $composer = shell_exec('cd '.base_path().' && composer install --no-interaction --no-dev --optimize-autoloader 2>&1');
            $log[] = ['step' => 'composer install', 'output' => trim($composer ?? '')];

            Artisan::call('migrate', ['--force' => true]);
            $log[] = ['step' => 'php artisan migrate', 'output' => trim(Artisan::output())];

            Artisan::call('optimize:clear');
            $log[] = ['step' => 'optimize:clear', 'output' => trim(Artisan::output())];

            Artisan::call('queue:restart');
            $log[] = ['step' => 'queue:restart', 'output' => trim(Artisan::output())];
        } finally {
            Artisan::call('up');
            $log[] = ['step' => 'maintenance off', 'output' => ''];
        }

        $this->activity->log('system.updated', 'Applied system update via admin panel');

        return response()->json([
            'success' => true,
            'commit' => $this->currentCommit(),
            'log' => $log,
        ]);
    }

    private function currentCommit(): array
    {
        $hash = trim(shell_exec('cd '.base_path().' && git rev-parse --short HEAD 2>/dev/null') ?? '');
        $subject = trim(shell_exec('cd '.base_path().' && git log -1 --pretty=format:\'%s\' 2>/dev/null') ?? '');
        $date = trim(shell_exec('cd '.base_path().' && git log -1 --pretty=format:\'%ar\' 2>/dev/null') ?? '');

        return compact('hash', 'subject', 'date');
    }

    private function currentBranch(): string
    {
        return trim(shell_exec('cd '.base_path().' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? 'unknown');
    }
}
