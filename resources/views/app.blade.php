<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#3b82f6" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <link rel="manifest" href="/manifest.json" />
    <link rel="icon" href="/favicon.ico" sizes="32x32" />
    <link rel="icon" href="/icon-192.png" type="image/png" sizes="192x192" />
    <link rel="apple-touch-icon" href="/icon-192.png" />

    {{-- Fallback title only when the SSR head lacks one — some crawlers read the first <title> tag they find. --}}
    @if (! str_contains((string) app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch()?->head, '<title'))
        <title inertia>{{ app(\App\Services\SettingsService::class)->appName() }}</title>
    @endif

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        window.__APP_NAME__ = @json(app(\App\Services\SettingsService::class)->appName());
        (function(){var t=localStorage.getItem('hc-theme');if(t==='light'){document.documentElement.classList.remove('dark');}else{document.documentElement.classList.add('dark');}})();
    </script>
    @routes(null, \Illuminate\Support\Facades\Vite::cspNonce())
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
