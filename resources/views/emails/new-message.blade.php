<!DOCTYPE html>
<html>
<body style="font-family:Inter,sans-serif;background:#09090b;color:#f4f4f5;padding:40px 20px;margin:0">
<div style="max-width:520px;margin:0 auto;background:#111113;border:1px solid #27272a;border-radius:12px;padding:32px">
    <h2 style="margin:0 0 8px;font-size:18px;color:#f4f4f5">New message from {{ $senderName }}</h2>
    <p style="color:#71717a;font-size:14px;margin:0 0 20px">{{ $preview }}</p>
    <a href="{{ $url }}" style="display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:600;font-size:14px">View Message</a>
</div>
</body>
</html>
