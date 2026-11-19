<?php

namespace App\Services\Update;

use Illuminate\Support\Facades\Cache;

/**
 * The live state of a running update, written to the cache so the panel can
 * show a progress bar even though the update request itself only answers at
 * the very end: the planned steps, the one running now with its output,
 * durations, a percentage and an estimate based on the previous run.
 */
class UpdateProgress
{
    public const KEY = 'hybridcore.update.progress';

    /** How long each step took last time, for the time estimate. */
    private const DURATIONS_KEY = 'hybridcore.update.durations';

    /** A run that stopped reporting this long ago was killed (timeout, OOM). */
    private const STALE_AFTER_MINUTES = 20;

    /**
     * Rough share of a typical run per step; refined by real durations once
     * an update has finished on this server.
     */
    private const WEIGHTS = [
        'maintenance on' => 1, 'git fetch' => 4, 'git pull' => 3, 'fix folder permissions' => 1,
        'composer install' => 20, 'database backup' => 6, 'php artisan migrate' => 8,
        'php artisan hybridcore:themes:sync' => 2, 'php artisan hybridcore:build' => 40,
        'restart SSR renderer' => 2, 'php artisan optimize:clear' => 2, 'php artisan queue:restart' => 1,
        'health check' => 3, 'maintenance off' => 1, 'restore database backup' => 8,
        'roll back database changes' => 4, 'git reset to previous version' => 2,
    ];

    /** @var array<int, array{step: string, output: string, ok: bool, seconds: float}> */
    private array $log = [];

    /** @var array<int, string> */
    private array $plan = [];

    /** @var array{step: string, started_at: string, output: string}|null */
    private ?array $current = null;

    private float $stepStartedAt = 0;

    private float $runStartedAt = 0;

    private float $lastFlush = 0;

    /** @var (callable(array<int, array{step: string, output: string, ok: bool}>): void)|null */
    private $onStep = null;

    /** @param  array<int, string>  $plan */
    public function start(array $plan, ?callable $onStep = null): void
    {
        $this->log = [];
        $this->onStep = $onStep;
        $this->plan = $plan;
        $this->current = null;
        $this->runStartedAt = microtime(true);
    }

    /**
     * Show the panel an update that was handed to a background process and
     * has not reported yet, so nobody starts a second one meanwhile.
     *
     * @param  array<int, string>  $plan
     */
    public function queued(array $plan): void
    {
        $this->start($plan);
        $this->current = ['step' => 'starting', 'started_at' => now()->toIso8601String(), 'output' => ''];
        $this->flush(true);
    }

    public static function isRunning(): bool
    {
        $progress = Cache::get(self::KEY);

        return ($progress['running'] ?? false) === true
            && now()->diffInMinutes($progress['updated_at'] ?? now(), absolute: true) < self::STALE_AFTER_MINUTES;
    }

    /** Mark a step as started, so the panel can show what is running right now. */
    public function begin(string $step): void
    {
        $this->stepStartedAt = microtime(true);
        $this->current = ['step' => $step, 'started_at' => now()->toIso8601String(), 'output' => ''];
        $this->flush(true);
    }

    /** Output arriving while a step runs, kept to the last few KB and flushed at most once a second. */
    public function live(string $chunk): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current['output'] = mb_substr($this->current['output'].$chunk, -4000);

        if (microtime(true) - $this->lastFlush >= 1) {
            $this->flush(true);
        }
    }

    public function record(string $step, string $output, bool $ok): void
    {
        $seconds = $this->current !== null && $this->current['step'] === $step
            ? round(microtime(true) - $this->stepStartedAt, 1)
            : 0.0;

        $this->log[] = ['step' => $step, 'output' => mb_substr($output, 0, 8000), 'ok' => $ok, 'seconds' => $seconds];
        $this->current = null;
        $this->flush(true);

        if ($this->onStep) {
            ($this->onStep)($this->log);
        }
    }

    /** @return array<int, array{step: string, output: string, ok: bool, seconds: float}> */
    public function log(): array
    {
        return $this->log;
    }

    public function elapsedSeconds(): int
    {
        return $this->runStartedAt ? (int) round(microtime(true) - $this->runStartedAt) : 0;
    }

    /** @param  array<string, mixed>|null  $result  the final result; null while still running */
    public function flush(bool $running, ?array $result = null): void
    {
        $this->lastFlush = microtime(true);

        Cache::put(self::KEY, [
            'running' => $running,
            'plan' => $this->plan,
            'log' => $this->log,
            'current' => $this->current,
            'percent' => $result !== null ? 100 : $this->percent(),
            'eta_seconds' => $result !== null ? 0 : $this->eta(),
            'elapsed_seconds' => $this->elapsedSeconds(),
            'result' => $result,
            'updated_at' => now()->toIso8601String(),
        ], 3600);
    }

    /** Keep this run's step timings for the next run's estimate. */
    public function rememberDurations(): void
    {
        Cache::forever(self::DURATIONS_KEY, collect($this->log)
            ->filter(fn (array $e) => in_array($e['step'], $this->plan, true))
            ->mapWithKeys(fn (array $e) => [$e['step'] => $e['seconds']])
            ->all());
    }

    /** Share of the planned work that is done, weighted by how long each step usually takes. */
    private function percent(): int
    {
        $durations = $this->durations();
        $weights = collect($this->plan)->mapWithKeys(fn (string $step) => [
            $step => isset($durations[$step]) ? max(0.5, (float) $durations[$step]) : (float) (self::WEIGHTS[$step] ?? 1),
        ])->all();
        $total = array_sum($weights) ?: 1;
        $done = 0.0;

        foreach (array_unique(array_column($this->log, 'step')) as $step) {
            $done += $weights[$step] ?? 0;
        }

        // The step running now counts in proportion to its usual duration, never as finished.
        if ($this->current !== null && isset($weights[$this->current['step']])) {
            $step = $this->current['step'];
            $expected = isset($durations[$step]) ? (float) $durations[$step] : (float) (self::WEIGHTS[$step] ?? 1) * 3;
            $share = $expected > 0 ? min(0.9, (microtime(true) - $this->stepStartedAt) / $expected) : 0.5;
            $done += $weights[$step] * $share;
        }

        return (int) min(99, floor($done / $total * 100));
    }

    /** Seconds left, from how long the remaining steps took last time; null before any update has finished here. */
    private function eta(): ?int
    {
        $durations = $this->durations();
        if ($durations === null) {
            return null;
        }

        $finished = array_flip(array_column($this->log, 'step'));
        $left = 0.0;
        foreach ($this->plan as $step) {
            if (isset($finished[$step])) {
                continue;
            }
            $left += (float) ($durations[$step] ?? 0);
            if ($this->current !== null && $this->current['step'] === $step) {
                $left -= microtime(true) - $this->stepStartedAt;
            }
        }

        return max(0, (int) round($left));
    }

    /** @return array<string, float|int>|null */
    private function durations(): ?array
    {
        $durations = Cache::get(self::DURATIONS_KEY);

        return is_array($durations) ? $durations : null;
    }
}
