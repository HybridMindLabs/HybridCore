<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IpBanController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $bans = IpBan::with('bannedBy')
            ->when($search !== '', fn ($q) => $q->where('ip', 'like', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (IpBan $ban) => [
                'id' => $ban->id,
                'ip' => $ban->ip,
                'reason' => $ban->reason,
                'expires_at' => $ban->expires_at?->toDateTimeString(),
                'is_active' => $ban->expires_at === null || $ban->expires_at->isFuture(),
                'banned_by' => $ban->bannedBy ? [
                    'id' => $ban->bannedBy->id,
                    'name' => $ban->bannedBy->name,
                ] : null,
                'created_at' => $ban->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/IpBans/Index', [
            'bans' => $bans,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:50', function (string $attr, mixed $value, \Closure $fail): void {
                if (str_contains($value, '/')) {
                    [$subnet, $bits] = array_pad(explode('/', $value, 2), 2, '');
                    if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! ctype_digit($bits) || (int) $bits > 32) {
                        $fail('The IP must be a valid IPv4 address or CIDR range (e.g. 192.168.1.0/24).');
                    }
                } elseif (! filter_var($value, FILTER_VALIDATE_IP)) {
                    $fail('The IP must be a valid IPv4 or IPv6 address.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        IpBan::create([
            ...$data,
            'banned_by' => auth()->id(),
        ]);

        return redirect()->route('admin.ip-bans.index')
            ->with('success', "IP {$data['ip']} banned.");
    }

    public function destroy(IpBan $ipBan): RedirectResponse
    {
        $ip = $ipBan->ip;
        $ipBan->delete();

        return redirect()->route('admin.ip-bans.index')
            ->with('success', "IP ban for {$ip} removed.");
    }
}
