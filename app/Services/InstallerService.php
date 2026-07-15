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

        // Writable directories
        foreach ([storage_path(), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir);
            $checks[] = [
                'label' => "{$relative}/ writable",
                'value' => is_writable($dir) ? 'Writable' : 'Not writable',
                'passed' => is_writable($dir),
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
