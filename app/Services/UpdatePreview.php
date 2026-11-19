<?php

namespace App\Services;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\Extension;
use App\Services\Extensions\ExtensionRequirements;
use Symfony\Component\Process\Process;

/**
 * What the next update would change, read from the fetched remote branch
 * before anything is applied: the version it brings, the database migrations
 * it will run, and whether this server and the enabled extensions meet its
 * requirements. A requirement this server does not meet blocks the update.
 */
class UpdatePreview
{
    public function __construct(private readonly ExtensionRequirements $requirements) {}

    /** @return array<string, mixed>|null null when there is nothing fetched to compare with */
    public function build(): ?array
    {
        $branch = trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD']) ?? '');
        $ref = 'origin/'.($branch ?: 'main');

        if ($this->git(['rev-parse', '--verify', '--quiet', $ref]) === null) {
            return null;
        }

        $incomingVersion = $this->incomingVersion($ref) ?? UpdateController::VERSION;
        $composer = json_decode($this->git(['show', $ref.':composer.json']) ?? '', true);
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];

        $checks = [];
        if (is_string($require['php'] ?? null)) {
            $checks[] = [
                'label' => "PHP {$require['php']}",
                'ok' => $this->meets(PHP_VERSION, $require['php']),
                'detail' => 'This server runs PHP '.PHP_VERSION,
            ];
        }
        foreach (array_keys($require) as $package) {
            if (str_starts_with((string) $package, 'ext-')) {
                $extension = substr($package, 4);
                $checks[] = [
                    'label' => "PHP extension {$extension}",
                    'ok' => extension_loaded($extension),
                    'detail' => extension_loaded($extension) ? 'Installed' : 'Not installed on this server',
                ];
            }
        }

        $extensions = Extension::where('enabled', true)->get()
            ->map(function (Extension $e) use ($incomingVersion) {
                $needs = $e->metadata['requires']['core'] ?? null;

                return is_string($needs) && ! $this->requirements->satisfies($incomingVersion, $needs)
                    ? ['name' => $e->name, 'detail' => "needs HybridCore {$needs}"]
                    : null;
            })
            ->filter()->values()->all();

        $migrations = array_values(array_filter(array_map(
            fn (string $file) => pathinfo($file, PATHINFO_FILENAME),
            explode("\n", trim($this->git(['diff', '--name-only', '--diff-filter=A', 'HEAD..'.$ref, '--', 'database/migrations']) ?? '')),
        )));

        return [
            'version_from' => UpdateController::VERSION,
            'version_to' => $incomingVersion,
            'changes' => trim($this->git(['diff', '--shortstat', 'HEAD..'.$ref]) ?? ''),
            'migrations' => $migrations,
            'checks' => $checks,
            'incompatible_extensions' => $extensions,
            'blocking' => collect($checks)->contains('ok', false),
        ];
    }

    /** @return array<int, string> requirements the incoming version has that this server lacks */
    public function blockingProblems(): array
    {
        $report = $this->build();

        return collect($report['checks'] ?? [])
            ->where('ok', false)
            ->map(fn (array $c) => "The new version needs {$c['label']}. {$c['detail']}.")
            ->values()->all();
    }

    private function incomingVersion(string $ref): ?string
    {
        $source = $this->git(['show', $ref.':app/Http/Controllers/Admin/UpdateController.php']) ?? '';

        return preg_match("/const VERSION = '([^']+)'/", $source, $m) ? $m[1] : null;
    }

    /** Composer-style constraint: "a || b" means either, spaces or commas inside one alternative mean all. */
    private function meets(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*\|\|?\s*/', trim($constraint)) as $alternative) {
            $parts = preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY);
            if ($parts && collect($parts)->every(fn (string $p) => $this->requirements->satisfies($version, $p))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, string>  $arguments */
    protected function git(array $arguments): ?string
    {
        $process = new Process(['git', ...$arguments], base_path(), null, null, 60);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
