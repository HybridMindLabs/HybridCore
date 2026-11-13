<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\CoreUpdater;
use App\Services\SettingsService;
use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public const VERSION = '0.11.2';

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
            'preflight' => fn () => app(CoreUpdater::class)->preflight(),
            'history' => fn () => ActivityLog::with('causer')
                ->where('event', 'system.updated')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'from' => $log->properties['from'] ?? null,
                    'to' => $log->properties['to'] ?? null,
                    'by' => $log->causer?->getAttribute('name'),
                    'at' => $log->created_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function updateChannel(Request $request): RedirectResponse
    {
        $request->validate([
            'channel' => ['required', 'in:stable,beta,dev'],
        ]);

        $this->settings->set('update_channel', $request->input('channel'));
        app(UpdateService::class)->forget();

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

    public function apply(CoreUpdater $updater): JsonResponse
    {
        abort_unless((bool) config('hybridcore.panel_updates'), 403, 'Panel updates are disabled on this installation.');

        $problems = $updater->preflight();
        if ($problems !== []) {
            return response()->json(['success' => false, 'message' => 'The update was not started.', 'problems' => $problems, 'log' => []], 409);
        }

        // composer + the asset build can outlast the web server's timeout;
        // keep going if the browser gives up — the page polls status().
        ignore_user_abort(true);
        set_time_limit(0);

        $result = $updater->run(pull: true, composer: true, build: true);

        return response()->json([...$result, 'commit' => $this->currentCommit()], $result['success'] ? 200 : 422);
    }

    /** Progress of the running (or last) update, for a page whose apply request timed out. */
    public function status(CoreUpdater $updater): JsonResponse
    {
        $progress = Cache::get(CoreUpdater::PROGRESS_KEY) ?? ['log' => [], 'result' => null];

        // A run that went silent was killed — report it as stopped so the page stops waiting.
        return response()->json([...$progress, 'running' => $updater->isRunning()]);
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
