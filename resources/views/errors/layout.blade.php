<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — @yield('title')</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: #09090b;
            color: #a1a1aa;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
        }

        /* Subtle dot grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* Ambient glow — same visual language as the rest of the site's
           hero sections, tinted per status via accent-from/accent-to. */
        .glow {
            position: fixed;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            opacity: 0.22;
            background: radial-gradient(circle, @yield('accent-from', '#60a5fa'), transparent 70%);
        }
        .glow-a { top: -140px; left: -120px; animation: drift-a 16s ease-in-out infinite; }
        .glow-b {
            bottom: -160px; right: -140px;
            background: radial-gradient(circle, @yield('accent-to', '#3b82f6'), transparent 70%);
            animation: drift-b 20s ease-in-out infinite;
        }

        @keyframes drift-a {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 20px) scale(1.08); }
        }
        @keyframes drift-b {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-24px, -16px) scale(1.06); }
        }

        .wrap {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 440px;
            width: 100%;
        }

        /* Staggered reveal — same choreography the rest of the site uses on
           page load, so an error page reads as this site, not a dead end. */
        .reveal { opacity: 0; animation: reveal-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes reveal-in {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Logo mark */
        .logo {
            width: 52px; height: 52px;
            margin: 0 auto 36px;
            border-radius: 14px;
            background: rgba(59,130,246,0.12);
            border: 1px solid rgba(59,130,246,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 800; letter-spacing: -0.02em;
            color: #60a5fa;
            animation-delay: 0ms;
        }

        /* Error code */
        .code {
            font-size: 80px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.04em;
            font-variant-numeric: tabular-nums;
            background: linear-gradient(135deg, @yield('accent-from', '#60a5fa'), @yield('accent-to', '#3b82f6'));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            animation-delay: 70ms;
        }

        /* Card */
        .card {
            background: #111113;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 40px 36px;
            animation-delay: 140ms;
            transition: border-color 0.3s;
        }

        h1 {
            color: #f4f4f5;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        p.desc {
            font-size: 14px;
            line-height: 1.65;
            color: #71717a;
            max-width: 320px;
            margin: 0 auto;
        }

        .reason-box {
            margin-top: 18px;
            padding: 12px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 13px;
            color: #a1a1aa;
            text-align: left;
        }
        .reason-box strong { color: #d4d4d8; font-weight: 600; }

        .actions {
            margin-top: 28px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        a.btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #3b82f6;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            transition: opacity 0.15s, transform 0.15s;
        }
        a.btn:hover { opacity: 0.88; transform: translateY(-1px); }
        a.btn:active { transform: translateY(0); }

        a.btn-secondary {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #71717a;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            transition: color 0.15s, border-color 0.15s, transform 0.15s;
        }
        a.btn-secondary:hover { color: #f4f4f5; border-color: rgba(255,255,255,0.22); transform: translateY(-1px); }
        a.btn-secondary:active { transform: translateY(0); }

        .divider {
            width: 36px; height: 2px;
            background: linear-gradient(90deg, @yield('accent-from', '#60a5fa'), @yield('accent-to', '#3b82f6'));
            border-radius: 2px;
            margin: 20px auto 20px;
            animation-delay: 110ms;
        }

        .meta {
            margin-top: 28px;
            font-size: 11px;
            color: #3f3f46;
            font-family: ui-monospace, 'Cascadia Code', monospace;
            letter-spacing: 0.05em;
            animation-delay: 220ms;
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal { animation: none; opacity: 1; }
            .glow-a, .glow-b { animation: none; }
        }
    </style>
</head>
<body>
    <div class="glow glow-a" aria-hidden="true"></div>
    <div class="glow glow-b" aria-hidden="true"></div>

    <div class="wrap">
        <div class="logo reveal">HC</div>

        <div class="code reveal">@yield('code')</div>
        <div class="divider reveal"></div>

        <div class="card reveal">
            <h1>@yield('title')</h1>
            <p class="desc">@yield('message')</p>

            @hasSection('reason')
                <div class="reason-box"><strong>Reason:</strong> @yield('reason')</div>
            @endif

            <div class="actions">
                @hasSection('secondary-action')
                    <a href="@yield('secondary-href', 'javascript:history.back()')" class="btn-secondary">@yield('secondary-action')</a>
                @endif
                <a href="{{ url('/') }}" class="btn">@yield('action', '← Back to home')</a>
            </div>
        </div>

        <p class="meta reveal">HybridCore &nbsp;·&nbsp; HTTP @yield('code')</p>
    </div>
</body>
</html>
