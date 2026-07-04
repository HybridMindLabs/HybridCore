<?php

use App\Jobs\QueryServerJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Services\AnalyticsService;
use App\Services\Bridge\BridgeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Query all active servers every 2 minutes
Schedule::call(function () {
    Server::active()->with('game')->get()->each(
        fn (Server $server) => QueryServerJob::dispatch($server)
    );
})->everyTwoMinutes()->name('query-servers')->withoutOverlapping();

// Prune snapshots older than 30 days
Schedule::call(function () {
    ServerSnapshot::where('recorded_at', '<', now()->subDays(30))->delete();
})->daily()->name('prune-server-snapshots');

// Prune page views older than 90 days
Schedule::call(function () {
    if (Schema::hasTable('page_views')) {
        app(AnalyticsService::class)->prune(90);
    }
})->daily()->name('prune-page-views');

// Weekly digest email for users with unread notifications
Schedule::command('hybridcore:email:digest')->weeklyOn(1, '09:00')->name('email-digest');

// Safety net for time-based achievements (e.g. "veteran") — most
// achievements are checked immediately at the relevant action instead.
Schedule::command('hybridcore:achievements:check')->daily()->name('check-achievements');

// Purge trashed news articles/comments older than their retention window
Schedule::command('model:prune', [
    '--model' => [NewsArticle::class, NewsComment::class],
])->daily()->name('prune-trash');

// Purge finished/expired bridge commands
Schedule::call(function () {
    app(BridgeService::class)->prune();
})->daily()->name('prune-bridge-commands');

// Heartbeats for the admin System Health page — see SystemHealthController::checks().
Schedule::call(function () {
    Cache::put('health.scheduler_last_run', now()->toIso8601String(), now()->addHour());
})->everyMinute()->name('health-scheduler-heartbeat');

Schedule::job(new QueueHeartbeatJob)->everyMinute()->name('health-queue-heartbeat');

// Prune abandoned extension ZIP import previews (uploaded but never
// confirmed or cancelled) — see ExtensionManager::previewZip()/confirmImport().
Schedule::call(function () {
    $dir = storage_path('app/extension-imports');

    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*.zip') ?: [] as $file) {
        if (filemtime($file) < now()->subHour()->timestamp) {
            @unlink($file);
        }
    }
})->hourly()->name('prune-extension-import-previews');
