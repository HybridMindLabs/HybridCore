<!DOCTYPE html>
<html>
<body style="font-family:Inter,sans-serif;background:#09090b;color:#f4f4f5;padding:40px 20px;margin:0">
<div style="max-width:520px;margin:0 auto;background:#111113;border:1px solid #27272a;border-radius:12px;padding:32px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <div style="width:40px;height:40px;background:#22c55e1a;border:1px solid #22c55e33;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px">✓</div>
        <div>
            <h2 style="margin:0 0 2px;font-size:16px;font-weight:700;color:#f4f4f5">Mail is working!</h2>
            <p style="margin:0;font-size:13px;color:#71717a">Your SMTP configuration is correct.</p>
        </div>
    </div>

    <div style="background:#18181b;border:1px solid #27272a;border-radius:8px;padding:16px;margin-bottom:20px">
        <p style="margin:0 0 8px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#71717a">Sent to</p>
        <p style="margin:0;font-size:14px;color:#f4f4f5">{{ $sentTo }}</p>
    </div>

    <p style="font-size:13px;color:#52525b;margin:0">
        This email was sent from the HybridCore admin panel to verify your mail settings.
        If you received it, email delivery is functioning correctly.
    </p>
</div>
</body>
</html>
