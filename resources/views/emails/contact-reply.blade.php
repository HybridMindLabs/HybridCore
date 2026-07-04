<!DOCTYPE html>
<html>
<body style="font-family:Inter,sans-serif;background:#09090b;color:#f4f4f5;padding:40px 20px;margin:0">
<div style="max-width:560px;margin:0 auto;background:#111113;border:1px solid #27272a;border-radius:12px;padding:32px">
    <h2 style="margin:0 0 4px;font-size:18px;color:#f4f4f5">Reply to your message</h2>
    <p style="color:#71717a;font-size:13px;margin:0 0 24px">Our team has responded to your contact request.</p>

    @if($msg->subject)
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px">
        <tr>
            <td style="padding:8px 12px;background:#18181b;border-radius:6px;color:#a1a1aa;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;width:80px">Subject</td>
            <td style="padding:8px 12px;background:#18181b;border-radius:6px;color:#f4f4f5;font-size:14px">{{ $msg->subject }}</td>
        </tr>
    </table>
    @endif

    <div style="margin-bottom:20px">
        <p style="color:#a1a1aa;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px">Reply</p>
        <div style="background:#18181b;border:1px solid #27272a;border-radius:8px;padding:16px;font-size:14px;line-height:1.7;color:#d4d4d8;white-space:pre-wrap">{{ $replyBody }}</div>
    </div>

    <div style="border-top:1px solid #27272a;padding-top:20px">
        <p style="color:#52525b;font-size:12px;margin:0 0 8px">Your original message:</p>
        <div style="background:#0f0f10;border:1px solid #1f1f23;border-radius:8px;padding:12px;font-size:13px;line-height:1.6;color:#71717a;white-space:pre-wrap">{{ $msg->message }}</div>
    </div>
</div>
</body>
</html>
