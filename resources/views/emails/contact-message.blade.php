<!DOCTYPE html>
<html>
<body style="font-family:Inter,sans-serif;background:#09090b;color:#f4f4f5;padding:40px 20px;margin:0">
<div style="max-width:560px;margin:0 auto;background:#111113;border:1px solid #27272a;border-radius:12px;padding:32px">
    <h2 style="margin:0 0 4px;font-size:18px;color:#f4f4f5">New contact message</h2>
    <p style="color:#71717a;font-size:13px;margin:0 0 24px">Received via the contact form</p>

    <table style="width:100%;border-collapse:collapse;margin-bottom:20px">
        <tr>
            <td style="padding:8px 12px;background:#18181b;border-radius:6px 6px 0 0;color:#a1a1aa;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">From</td>
            <td style="padding:8px 12px;background:#18181b;border-radius:6px 6px 0 0;color:#f4f4f5;font-size:14px">{{ $msg->name }} &lt;{{ $msg->email }}&gt;</td>
        </tr>
        @if($msg->subject)
        <tr>
            <td style="padding:8px 12px;background:#1c1c1e;color:#a1a1aa;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Subject</td>
            <td style="padding:8px 12px;background:#1c1c1e;color:#f4f4f5;font-size:14px">{{ $msg->subject }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:8px 12px;background:#18181b;border-radius:0 0 6px 6px;color:#a1a1aa;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Date</td>
            <td style="padding:8px 12px;background:#18181b;border-radius:0 0 6px 6px;color:#f4f4f5;font-size:14px">{{ $msg->created_at->format('d M Y, H:i') }}</td>
        </tr>
    </table>

    <div style="background:#18181b;border:1px solid #27272a;border-radius:8px;padding:16px;font-size:14px;line-height:1.7;color:#d4d4d8;white-space:pre-wrap">{{ $msg->message }}</div>

    <div style="margin-top:24px">
        <a href="{{ route('admin.contact.index') }}" style="display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:600;font-size:14px">View in Admin Panel</a>
    </div>
</div>
</body>
</html>
