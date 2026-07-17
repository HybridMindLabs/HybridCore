<?php

namespace App\Console\Commands;

use App\Games\GameDriverRegistry;
use App\Models\Server;
use Illuminate\Console\Command;

/**
 * Queries a server and shows everything that came back — or exactly why nothing
 * did. The scheduled job logs a one-line warning and the panel just says
 * "offline", which is no help at all when a server that plainly works refuses to
 * show up.
 */
class QueryTestCommand extends Command
{
    protected $signature = 'hybridcore:query-test
                            {target : A server ID, or an address like 1.2.3.4:27015}
                            {--driver= : Driver slug to use (required with an address)}
                            {--raw : Dump every field the protocol returned}';

    protected $description = 'Query a game server and show the raw result or the failure';

    public function handle(GameDriverRegistry $registry): int
    {
        $target = (string) $this->argument('target');

        if (ctype_digit($target)) {
            $server = Server::with('game')->find($target);

            if ($server === null) {
                $this->components->error("No server with ID {$target}.");
                $this->listServers();

                return self::FAILURE;
            }

            $host = $server->ip;
            $port = (int) ($server->query_port ?: $server->port);
            $driver = (string) $server->game?->query_driver;
        } else {
            [$host, $port] = array_pad(explode(':', $target, 2), 2, null);
            $port = (int) $port;
            $driver = (string) $this->option('driver');

            if ($driver === '' || $port === 0) {
                $this->components->error('Use  host:port --driver=<slug>. Known drivers:');
                $this->line('  '.implode(', ', $registry->slugs()));

                return self::FAILURE;
            }
        }

        $this->components->twoColumnDetail('<fg=gray>Address</>', "{$host}:{$port}");
        $this->components->twoColumnDetail('<fg=gray>Driver</>', $driver);
        $this->newLine();

        return $this->probe($registry, $host, $port, $driver);
    }

    private function probe(GameDriverRegistry $registry, string $host, int $port, string $driver): int
    {
        $impl = $registry->driverFor($driver);

        if ($impl === null) {
            $this->components->error("No driver handles '{$driver}'.");
            $this->line('  Known: '.implode(', ', $registry->slugs()));

            return self::FAILURE;
        }

        $started = microtime(true);
        $result = $impl->query($host, $port, 5.0);
        $took = round((microtime(true) - $started) * 1000);

        if (! $result->online) {
            $this->components->error("Offline after {$took}ms");
            $this->line('  '.($result->failureReason ?? 'no reason given'));
            $this->newLine();
            $this->hints("{$host}:{$port}", $driver);

            return self::FAILURE;
        }

        $this->components->info("Online — answered in {$result->ping}ms");
        $this->components->twoColumnDetail('<fg=gray>Name</>', (string) ($result->name ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Map</>', (string) ($result->map ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Players</>', $result->playersOnline.' / '.$result->playersMax);
        $this->components->twoColumnDetail('<fg=gray>Version</>', (string) ($result->version ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Password</>', $result->passwordProtected ? 'yes' : 'no');
        $this->components->twoColumnDetail('<fg=gray>Player list</>', count($result->players).' returned');

        if ($this->option('raw')) {
            $this->newLine();
            $this->line('<fg=yellow>Players:</>');
            foreach ($result->players as $p) {
                $this->line("  - {$p->name}  (score {$p->score}, {$p->duration}s)");
            }
        }

        return self::SUCCESS;
    }

    /** Things worth checking before assuming the server is down. */
    private function hints(string $host, string $driver): void
    {
        $port = (int) (explode(':', $host)[1] ?? 0);

        $this->components->bulletList(array_filter([
            'The query port is often NOT the game port. Rust: game+2, ARK: 27015, 7 Days to Die: game+1, Unturned: game+1.',
            str_contains($driver, 'minecraft')
                ? 'Minecraft: this uses Server List Ping on the game port — it does not need enable-query.'
                : null,
            'Check the port is reachable: nc -uzv '.explode(':', $host)[0].' '.$port,
            'A firewall may allow the game port while dropping UDP on the query port.',
            'Try another protocol: --driver=source is worth a go for any Source-engine game.',
        ]));
    }

    private function listServers(): void
    {
        $servers = Server::with('game')->get();

        if ($servers->isEmpty()) {
            $this->line('  No servers configured yet.');

            return;
        }

        $this->table(
            ['ID', 'Address', 'Game', 'Driver'],
            $servers->map(fn (Server $s) => [
                $s->id,
                $s->ip.':'.$s->port,
                $s->game?->name ?? '—',
                $s->game?->query_driver ?? '—',
            ]),
        );
    }
}
