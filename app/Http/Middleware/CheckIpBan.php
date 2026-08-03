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
        if (! $this->isExemptAdminSession($request)) {
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

    /**
     * Only an already-signed-in admin skips the ban check — so one admin
     * getting banned alongside their IP range (office NAT, VPN exit) doesn't
     * lock out the rest of the staff mid-session. Anonymous requests,
     * including /admin/login itself, are never exempt: without this, a
     * banned IP could still hit the (throttled) admin login freely.
     */
    private function isExemptAdminSession(Request $request): bool
    {
        return $request->is('admin*') && $request->user()?->is_admin === true;
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
