<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ServerQueryService;
use Illuminate\Console\Command;

/**
 * Runs the whole server sweep right now, in the foreground, and prints what came
 * back. The scheduled job does the same on a timer through the queue; this is
 * for doing it on demand — straight after setup, on a host without a worker, or
 * to see the results while debugging.
 */
class QueryServersCommand extends Command
{
    protected $signature = 'hybridcore:query-servers
                            {--game= : Only query servers for this game slug}';

    protected $description = 'Query every active server now and show the result';

    public function handle(ServerQueryService $service): int
    {
        $servers = Server::active()
            ->with('game')
            ->when($this->option('game'), fn ($q, $slug) => $q->whereHas('game', fn ($g) => $g->where('slug', $slug)))
            ->get();

        if ($servers->isEmpty()) {
            $this->components->warn('No active servers to query.');

            return self::SUCCESS;
        }

        $this->components->info("Querying {$servers->count()} server(s)…");
        $started = microtime(true);

        $rows = [];
        $online = 0;

        foreach ($servers as $server) {
            $snapshot = $service->query($server);

            if ($snapshot->is_online) {
                $online++;
                $rows[] = [
                    '<fg=green>online</>',
                    $server->address,
                    $server->game?->name ?? '—',
                    $snapshot->players_online.'/'.$snapshot->players_max,
                    ($snapshot->ping ?? '—').'ms',
                    $this->trim((string) $snapshot->name),
                ];
            } else {
                $rows[] = [
                    '<fg=red>offline</>',
                    $server->address,
                    $server->game?->name ?? '—',
                    '—',
                    '—',
                    "<fg=gray>{$this->trim((string) $snapshot->failure_reason)}</>",
                ];
            }
        }

        $took = round((microtime(true) - $started) * 1000);

        $this->table(['Status', 'Address', 'Game', 'Players', 'Ping', 'Name / reason'], $rows);
        $this->components->info("{$online}/{$servers->count()} online — took {$took}ms");

        return self::SUCCESS;
    }

    private function trim(string $value): string
    {
        return mb_strlen($value) > 40 ? mb_substr($value, 0, 39).'…' : $value;
    }
}
