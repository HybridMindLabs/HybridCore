<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\ServerQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class QueryServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 15;

    public int $tries = 1;

    public function __construct(public readonly Server $server) {}

    public function handle(ServerQueryService $service): void
    {
        $service->query($this->server);
        Cache::forget('server.snapshot.'.$this->server->id);
    }
}
