<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, sans-serif; background: #09090b; color: #f4f4f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 480px; text-align: center; padding: 40px 24px; }
        .code { font-size: 72px; font-weight: 700; color: #ef4444; line-height: 1; }
        h1 { font-size: 24px; margin: 16px 0 8px; }
        p { color: #71717a; font-size: 15px; line-height: 1.6; }
        .reason { margin-top: 16px; padding: 12px 16px; background: #18181b; border: 1px solid #27272a; border-radius: 8px; font-size: 14px; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">403</div>
        <h1>Your IP has been banned</h1>
        <p>Access to this site has been restricted for your IP address.</p>
        @if($reason)
            <div class="reason">Reason: {{ $reason }}</div>
        @endif
    </div>
</body>
</html>
