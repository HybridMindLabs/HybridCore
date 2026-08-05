<?php

namespace App\Support;

/** Coarse browser/OS parsing for display purposes (session lists, login alerts) — not a security check. */
final class UserAgent
{
    private function __construct() {}

    /** @return array{browser: string, os: string, mobile: bool} */
    public static function parse(string $ua): array
    {
        $browser = match (true) {
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome')
                && ! str_contains($ua, 'Edg')
                && ! str_contains($ua, 'OPR') => 'Chrome',
            str_contains($ua, 'Safari')
                && ! str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'OPR') => 'Opera',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone')
                || str_contains($ua, 'iPad') => 'iOS',
            default => 'Unknown OS',
        };

        $mobile = str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone');

        return ['browser' => $browser, 'os' => $os, 'mobile' => $mobile];
    }

    /** "Chrome on Windows" — the one-line summary a login alert or session row shows. */
    public static function summarize(string $ua): string
    {
        $parsed = self::parse($ua);

        return "{$parsed['browser']} on {$parsed['os']}";
    }
}
