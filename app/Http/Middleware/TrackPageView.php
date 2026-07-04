<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
        'ia_archiver', 'wget', 'curl', 'python-requests', 'go-http-client',
        'headlesschrome', 'phantomjs', 'selenium', 'lighthouse',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            $data = [
                'session_id' => substr(session()->getId(), 0, 40),
                'user_id' => $request->user()?->id,
                'ip_hash' => hash('sha256', $request->ip()),
                'path' => substr($request->path(), 0, 500),
                'route_name' => $request->route()?->getName(),
                'device_type' => $this->deviceType($request->userAgent() ?? ''),
                'country_code' => null,
                'is_bot' => $this->isBot($request->userAgent() ?? ''),
            ];

            // Write after response is sent — zero latency impact on the user
            app()->terminating(static function () use ($data): void {
                PageView::create($data);
            });
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        // Cache table-existence so it only hits the DB schema once per process
        if (! Cache::remember('analytics.table_exists', 3600, fn () => Schema::hasTable('page_views'))) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if ($request->is('admin/*', 'api/*', '_debugbar/*')) {
            return false;
        }

        return true;
    }

    private function isBot(string $ua): bool
    {
        $ua = strtolower($ua);
        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function deviceType(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
