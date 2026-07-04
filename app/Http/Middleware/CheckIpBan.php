<?php

namespace App\Http\Middleware;

use App\Models\IpBan;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CheckIpBan
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->is('admin*')) {
            try {
                $ip = $request->ip();
                $ban = IpBan::active()->get()->first(function (IpBan $ban) use ($ip): bool {
                    return str_contains($ban->ip, '/') ? $this->ipInCidr($ip, $ban->ip) : $ban->ip === $ip;
                });
                if ($ban) {
                    return response()->view('errors.ip-banned', ['reason' => $ban->reason], 403);
                }
            } catch (QueryException) {
                // table may not exist yet (installer / fresh migration)
            }
        }

        return $next($request);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = $bits >= 32 ? -1 : ~((1 << (32 - (int) $bits)) - 1);

        return ($ip & $mask) === ($subnet & $mask);
    }
}
