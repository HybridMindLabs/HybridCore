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

        .wrap {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 440px;
            width: 100%;
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
        }

        /* Card */
        .card {
            background: #111113;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 40px 36px;
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
            transition: opacity 0.15s;
        }
        a.btn:hover { opacity: 0.88; }

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
            transition: color 0.15s, border-color 0.15s;
        }
        a.btn-secondary:hover { color: #f4f4f5; border-color: rgba(255,255,255,0.22); }

        .divider {
            width: 36px; height: 2px;
            background: linear-gradient(90deg, @yield('accent-from', '#60a5fa'), @yield('accent-to', '#3b82f6'));
            border-radius: 2px;
            margin: 20px auto 20px;
        }

        .meta {
            margin-top: 28px;
            font-size: 11px;
            color: #3f3f46;
            font-family: ui-monospace, 'Cascadia Code', monospace;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">HC</div>

        <div class="card">
            <div class="code">@yield('code')</div>
            <div class="divider"></div>
            <h1>@yield('title')</h1>
            <p class="desc">@yield('message')</p>

            <div class="actions">
                @hasSection('secondary-action')
                    <a href="@yield('secondary-href', 'javascript:history.back()')" class="btn-secondary">@yield('secondary-action')</a>
                @endif
                <a href="{{ url('/') }}" class="btn">@yield('action', '← Back to home')</a>
            </div>
        </div>

        <p class="meta">HybridCore &nbsp;·&nbsp; HTTP @yield('code')</p>
    </div>
</body>
</html>
