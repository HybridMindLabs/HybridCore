<?php

namespace App\Support;

class HostSafety
{
    /**
     * Reject loopback/private/link-local/reserved targets — without this, an
     * admin-supplied game-server host is an SSRF primitive: it resolves and
     * connects on every scheduled query, so it can probe the internal network
     * or the 169.254.169.254 cloud metadata endpoint on a timer.
     */
    public static function isSafePublicHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $resolved = gethostbyname($host);

            if ($resolved === $host || ! filter_var($resolved, FILTER_VALIDATE_IP)) {
                return false;
            }

            $ip = $resolved;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
