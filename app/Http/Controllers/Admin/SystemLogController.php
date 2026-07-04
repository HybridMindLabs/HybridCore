<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemLogController extends Controller
{
    public function index(): Response
    {
        $logPath = storage_path('logs/laravel.log');
        $exists = file_exists($logPath);
        $sizeKb = $exists ? round(filesize($logPath) / 1024, 1) : 0;
        $lines = 0;
        $tail = [];

        if ($exists && $sizeKb > 0) {
            $file = new SplFileObject($logPath, 'r');
            $file->seek(PHP_INT_MAX);
            $lines = $file->key();

            // Last 50 lines
            $start = max(0, $lines - 50);
            $file->seek($start);
            while (! $file->eof()) {
                $line = rtrim($file->fgets());
                if ($line !== '') {
                    $tail[] = $line;
                }
            }
            $tail = array_slice($tail, -50);
        }

        return Inertia::render('Admin/SystemLogs/Index', [
            'logSizeKb' => $sizeKb,
            'logPath' => 'storage/logs/laravel.log',
            'lineCount' => $lines,
            'tail' => $tail,
        ]);
    }

    public function download(): BinaryFileResponse
    {
        $logPath = storage_path('logs/laravel.log');
        abort_unless(file_exists($logPath), 404, 'Log file not found.');

        return response()->download($logPath, 'laravel-'.now()->format('Y-m-d').'.log');
    }

    public function clear(): RedirectResponse
    {
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        return back()->with('success', 'Log file cleared.');
    }
}
