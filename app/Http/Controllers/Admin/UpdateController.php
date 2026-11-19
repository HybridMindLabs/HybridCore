<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\CoreUpdater;
use App\Services\SettingsService;
use App\Services\UpdateLauncher;
use App\Services\UpdatePreview;
use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpdateController extends Controller
{
    public const VERSION = '0.12.0';

    /** Activity-log events shown in the update history. */
    private const HISTORY_EVENTS = ['system.updated', 'system.update_failed', 'system.reverted'];

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
            'plan' => fn () => app(CoreUpdater::class)->plan(),
            'history' => fn () => $this->history(),
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

    /** @return array<int, array<string, mixed>> */
    private function history(): array
    {
        $head = $this->currentCommit()['hash'];
        $latest = ActivityLog::where('event', 'system.updated')->latest('id')->value('id');

        return ActivityLog::with('causer')
            ->whereIn('event', self::HISTORY_EVENTS)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'from' => $log->properties['from'] ?? null,
                'to' => $log->properties['to'] ?? null,
                'seconds' => $log->properties['seconds'] ?? null,
                'backup' => $log->properties['backup'] ?? null,
                'log_url' => ! empty($log->properties['log_file']) ? route('admin.updates.log', $log) : null,
                'can_revert' => $log->id === $latest
                    && ! empty($log->properties['from'])
                    && ($log->properties['from'] ?? null) !== ($log->properties['to'] ?? null)
                    && ($log->properties['to'] ?? null) === $head,
                'by' => $log->causer?->getAttribute('name'),
                'at' => $log->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    public function check(UpdatePreview $preview): JsonResponse
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
            'report' => empty($commits) ? null : $preview->build(),
        ]);
    }

    public function apply(Request $request, CoreUpdater $updater, UpdatePreview $preview, UpdateLauncher $launcher): JsonResponse
    {
        abort_unless((bool) config('hybridcore.panel_updates'), 403, 'Panel updates are disabled on this installation.');

        $problems = [...$updater->preflight(), ...$preview->blockingProblems()];
        if ($problems !== []) {
            return response()->json(['success' => false, 'message' => 'The update was not started.', 'problems' => $problems, 'log' => []], 409);
        }

        // Preferred: a process of its own, out of reach of request time limits.
        if (config('hybridcore.update_in_background')) {
            $updater->markQueued($updater->plan());
            if ($launcher->launch(['--panel', '--build', '--user='.$request->user()->id])) {
                return response()->json(['started' => true], 202);
            }
        }

        // Fallback: run inside this request. composer + the asset build can
        // outlast the web server's timeout; keep going if the browser gives up.
        ignore_user_abort(true);
        set_time_limit(0);

        $result = $updater->run(pull: true, composer: true, build: true);

        return response()->json([...$result, 'commit' => $this->currentCommit()], $result['success'] ? 200 : 422);
    }

    /** Roll the most recent update back to the version it started from. */
    public function revert(Request $request, ActivityLog $update, CoreUpdater $updater, UpdateLauncher $launcher): JsonResponse
    {
        abort_unless((bool) config('hybridcore.panel_updates'), 403, 'Panel updates are disabled on this installation.');

        $restore = $request->boolean('restore_database');
        $problems = $this->revertProblems($update, $restore);
        if ($updater->isRunning()) {
            $problems[] = 'Another update is already running.';
        }
        if ($problems !== []) {
            return response()->json(['success' => false, 'message' => 'The rollback was not started.', 'problems' => $problems], 409);
        }

        if (config('hybridcore.update_in_background')) {
            $updater->markQueued($updater->revertPlan($restore));
            $arguments = ['--panel', '--revert='.$update->id, '--user='.$request->user()->id];
            if ($restore) {
                $arguments[] = '--restore-database';
            }
            if ($launcher->launch($arguments)) {
                return response()->json(['started' => true], 202);
            }
        }

        ignore_user_abort(true);
        set_time_limit(0);

        $result = $updater->revert($update->properties, $restore);

        return response()->json([...$result, 'commit' => $this->currentCommit()], $result['success'] ? 200 : 422);
    }

    /** The full log an update left behind, as a JSON download. */
    public function log(ActivityLog $update): BinaryFileResponse
    {
        abort_unless(in_array($update->event, self::HISTORY_EVENTS, true), 404);

        $file = basename((string) ($update->properties['log_file'] ?? ''));
        $path = storage_path('app/updates/'.$file);
        abort_unless($file !== '' && preg_match('/^update-[\d-]+\.json$/', $file) && is_file($path), 404);

        return response()->download($path, $file, ['Content-Type' => 'application/json']);
    }

    /** @return array<int, string> why this update cannot be rolled back right now */
    private function revertProblems(ActivityLog $update, bool $restore): array
    {
        $properties = $update->properties ?? [];
        $latest = ActivityLog::where('event', 'system.updated')->latest('id')->value('id');

        return array_values(array_filter([
            $update->event !== 'system.updated' ? 'Only a completed update can be rolled back.' : null,
            $update->id !== $latest ? 'Only the most recent update can be rolled back.' : null,
            empty($properties['from']) || ($properties['from'] ?? null) === ($properties['to'] ?? null) ? 'This update did not change the code.' : null,
            ($properties['to'] ?? null) !== $this->currentCommit()['hash'] ? 'The site is no longer on the version this update installed.' : null,
            $restore && empty($properties['backup']) ? 'No database backup was taken before this update.' : null,
        ]));
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
