<?php

namespace App\Services;

class DatabaseBackupService
{
    private const FILENAME_PREFIX = 'hybridcore-db-';

    public function findMysqldump(): ?string
    {
        // Honour explicit override. Read via config, not env(): env() returns
        // null once `config:cache` has run, silently ignoring the operator's
        // setting on exactly the production hosts that need it.
        if ($override = config('hybridcore.mysqldump_path')) {
            return is_executable($override) ? $override : null;
        }

        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/bin/mysqldump'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $found = trim((string) shell_exec('which mysqldump 2>/dev/null'));

        return ($found && is_executable($found)) ? $found : null;
    }

    /** Run mysqldump and save to storage/app/backups/. Shared by the manual button and the scheduled job. */
    public function create(): array
    {
        $mysqldump = $this->findMysqldump();
        if ($mysqldump === null) {
            return ['ok' => false, 'error' => 'mysqldump not found on this server.'];
        }

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

        $filename = self::FILENAME_PREFIX.now()->format('Y-m-d-His').'.sql';
        $filepath = $dir.'/'.$filename;

        // Write a temporary .cnf to avoid password in the process list
        $cnf = tempnam(sys_get_temp_dir(), 'mysqldump_');
        file_put_contents($cnf, "[client]\npassword=".addslashes($password)."\n");
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

            return ['ok' => false, 'error' => 'mysqldump failed. Check server logs.'];
        }

        return ['ok' => true, 'filename' => $filename];
    }

    /** Delete the oldest database backups beyond $keep. Returns how many were deleted. */
    public function prune(int $keep): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            return 0;
        }

        $files = glob($dir.'/'.self::FILENAME_PREFIX.'*.sql') ?: [];
        if (count($files) <= $keep) {
            return 0;
        }

        // Filenames carry a sortable timestamp (Y-m-d-His), so lexical sort
        // is chronological sort — no need to stat() every file.
        rsort($files);

        $deleted = 0;
        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
            $deleted++;
        }

        return $deleted;
    }
}
