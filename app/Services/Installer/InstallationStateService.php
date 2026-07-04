<?php

namespace App\Services\Installer;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\Setting;
use App\Services\InstallerService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for "is HybridCore installed?".
 *
 * The app is considered installed when AT LEAST ONE trusted marker exists:
 *   1. storage/installed.lock          (filesystem — survives DB outages)
 *   2. APP_INSTALLED=true              (.env / config('app.installed'))
 *   3. settings table key installed_at (database)
 *
 * A database outage therefore never makes an installed app fall back into
 * the installer, and a missing lock file alone doesn't either.
 */
class InstallationStateService
{
    public function __construct(private readonly InstallerService $installer) {}

    public function isInstalled(): bool
    {
        return $this->hasLockFile()
            || $this->hasEnvInstalledFlag()
            || $this->hasDatabaseInstalledFlag();
    }

    public function hasLockFile(): bool
    {
        return file_exists($this->lockPath());
    }

    public function hasEnvInstalledFlag(): bool
    {
        return (bool) config('app.installed', false);
    }

    public function hasDatabaseInstalledFlag(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return false;
            }

            return Setting::where('key', 'installed_at')->exists();
        } catch (Throwable) {
            // DB unreachable — this marker is simply unknown, not "false-and-fatal".
            return false;
        }
    }

    /**
     * Write ALL installation markers. Called by the installer on success
     * and by the hybridcore:install-lock command.
     *
     * @param  array<string, mixed>  $metadata  Extra metadata for the lock file.
     */
    public function markInstalled(array $metadata = []): void
    {
        $this->lock($metadata);

        // .env flag — reuses the safe single-key env writer. Skipped in tests
        // so the test suite never mutates the developer's real .env file.
        if (! app()->runningUnitTests()) {
            try {
                $this->installer->writeEnvValues(['APP_INSTALLED' => 'true']);
            } catch (Throwable) {
                // .env not writable — lock file already guarantees installed state.
            }
        }

        // Database flag.
        try {
            if (Schema::hasTable('settings')) {
                app(SettingsService::class)->set('installed_at', now()->toIso8601String());
            }
        } catch (Throwable) {
            // DB unavailable — same reasoning as above.
        }
    }

    /** @param array<string, mixed> $metadata */
    public function lock(array $metadata = []): void
    {
        $payload = array_merge([
            'installed' => true,
            'installed_at' => now()->toIso8601String(),
            'version' => UpdateController::VERSION,
            'environment' => app()->environment(),
        ], $metadata);

        file_put_contents(
            $this->lockPath(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * Remove all installation markers. Destructive — requires $force.
     */
    public function unlock(bool $force = false): void
    {
        if (! $force) {
            throw new RuntimeException('Unlocking the installer requires force. This re-opens /install to the public.');
        }

        if ($this->hasLockFile()) {
            unlink($this->lockPath());
        }

        if (! app()->runningUnitTests()) {
            try {
                $this->installer->writeEnvValues(['APP_INSTALLED' => 'false']);
            } catch (Throwable) {
                // Ignore — best effort.
            }
        }

        try {
            if (Schema::hasTable('settings')) {
                Setting::where('key', 'installed_at')->delete();
                Cache::forget('hybridcore.settings');
            }
        } catch (Throwable) {
            // DB unavailable — nothing to clear.
        }
    }

    /**
     * Diagnostic snapshot of every marker plus the final verdict.
     *
     * @return array{lock_file: bool, lock_metadata: array<string, mixed>|null, env_flag: bool, database_flag: bool|string, installed: bool}
     */
    public function status(): array
    {
        $lockMetadata = null;

        if ($this->hasLockFile()) {
            $raw = (string) file_get_contents($this->lockPath());
            $decoded = json_decode($raw, true);
            $lockMetadata = is_array($decoded) ? $decoded : ['raw' => trim($raw)];
        }

        try {
            $dbFlag = Schema::hasTable('settings')
                ? Setting::where('key', 'installed_at')->exists()
                : false;
        } catch (Throwable) {
            $dbFlag = 'unavailable';
        }

        return [
            'lock_file' => $this->hasLockFile(),
            'lock_metadata' => $lockMetadata,
            'env_flag' => $this->hasEnvInstalledFlag(),
            'database_flag' => $dbFlag,
            'installed' => $this->isInstalled(),
        ];
    }

    private function lockPath(): string
    {
        return storage_path('installed.lock');
    }
}
