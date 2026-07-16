<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class InstallerService
{
    public function checkRequirements(): array
    {
        $checks = [];

        // PHP version
        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
        $checks[] = [
            'label' => 'PHP >= 8.3',
            'value' => PHP_VERSION,
            'passed' => $phpOk,
            'critical' => true,
        ];

        // Required extensions
        foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'bcmath'] as $ext) {
            $checks[] = [
                'label' => "ext-{$ext}",
                'value' => extension_loaded($ext) ? 'Loaded' : 'Missing',
                'passed' => extension_loaded($ext),
                'critical' => true,
            ];
        }

        // Writable directories.
        //
        // Every directory the app actually writes to is listed, not just their
        // parents: storage/ is routinely writable while storage/framework/cache
        // underneath it is not (typically left owned by root after an artisan
        // command was run with sudo), which passed this check and then failed at
        // runtime instead.
        foreach ([
            storage_path('logs'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            base_path('bootstrap/cache'),
        ] as $dir) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir);
            $failure = $this->checkDirectoryIsWritable($dir);

            $checks[] = [
                'label' => "{$relative}/ writable",
                'value' => $failure ?? 'Writable',
                'passed' => $failure === null,
                'critical' => true,
            ];
        }

        // .env file
        $envExists = file_exists(base_path('.env'));
        $checks[] = [
            'label' => '.env file',
            'value' => $envExists ? 'Found' : 'Missing (will be created)',
            'passed' => $envExists || is_writable(base_path()),
            'critical' => true,
        ];

        // APP_KEY
        $hasKey = ! empty(config('app.key'));
        $checks[] = [
            'label' => 'APP_KEY',
            'value' => $hasKey ? 'Set' : 'Not set (will be generated)',
            'passed' => true, // We generate it in finish step if missing
            'critical' => false,
        ];

        return $checks;
    }

    /**
     * Returns null when the directory can be written to, otherwise why not.
     *
     * Writes a real file rather than trusting is_writable(), which reports on
     * the permission bits alone and so misses ACLs, read-only mounts and
     * SELinux — exactly the setups where this goes wrong.
     */
    private function checkDirectoryIsWritable(string $dir): ?string
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return 'Missing (could not be created)';
        }

        $probe = $dir.DIRECTORY_SEPARATOR.'.hybridcore-write-test';

        if (@file_put_contents($probe, 'ok') === false) {
            return 'Not writable by '.$this->currentUser();
        }

        @unlink($probe);

        return null;
    }

    /** The user PHP runs as — the one that needs to own these directories. */
    private function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && isset($info['name'])) {
                return (string) $info['name'];
            }
        }

        return get_current_user() ?: 'the web server user';
    }

    public function allRequirementsPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['critical'] && ! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Try to reach the database.
     *
     * Returns null on success, otherwise the reason it failed. The caller shows
     * that reason verbatim: "check your credentials" is useless advice when the
     * real answer is "unknown database" or "host unreachable", and the person
     * installing has no logs to fall back on yet.
     */
    public function testDatabaseConnection(string $host, int $port, string $database, string $username, string $password): ?string
    {
        config([
            'database.connections.installer_test' => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
                'options' => [1006 => 5], // PDO::MYSQL_ATTR_CONNECT_TIMEOUT
            ],
        ]);

        try {
            DB::purge('installer_test');
            DB::connection('installer_test')->select('SELECT 1');

            return null;
        } catch (Throwable $e) {
            // Prefer the driver's own message ("Access denied…", "Unknown
            // database…"); it names the actual problem. It never contains the
            // password — only the username and whether one was supplied.
            return $e->getPrevious()?->getMessage() ?? $e->getMessage();
        } finally {
            DB::disconnect('installer_test');
        }
    }

    public function writeEnvValues(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $example = base_path('.env.example');
            copy(file_exists($example) ? $example : $envPath, $envPath);
        }

        $content = file_get_contents($envPath) ?: '';

        foreach ($values as $key => $value) {
            $escaped = $this->escapeEnvValue((string) $value);
            if (preg_match("/^{$key}=/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= PHP_EOL."{$key}={$escaped}";
            }
        }

        file_put_contents($envPath, $content);
    }

    public function runMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    public function generateAppKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    public function createLockFile(): void
    {
        file_put_contents(
            storage_path('installed.lock'),
            'HybridCore installed at '.now()->toIso8601String().PHP_EOL,
        );
    }

    public function isInstalled(): bool
    {
        return file_exists(storage_path('installed.lock'));
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        // Quote if value contains spaces or special characters
        if (preg_match('/[\s#"\'\\\\]/', $value)) {
            $value = '"'.addslashes($value).'"';
        }

        return $value;
    }
}
