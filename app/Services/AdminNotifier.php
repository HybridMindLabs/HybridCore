<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;

/**
 * Sends operational alerts to the staff allowed to act on them, honouring each
 * admin's per-alert channel choice (Admin > Profile > Notifications).
 */
class AdminNotifier
{
    /** @var array<string, array{label: string, description: string, permission: string}> */
    public const ALERTS = [
        'server_offline' => [
            'label' => 'Server goes offline',
            'description' => 'A game server that was online stops answering.',
            'permission' => 'servers.view',
        ],
        'contact_message' => [
            'label' => 'New contact message',
            'description' => 'Someone sends a message through the contact form.',
            'permission' => 'settings.view',
        ],
        'content_report' => [
            'label' => 'New content report',
            'description' => 'A member reports a comment, review or profile.',
            'permission' => 'content.view',
        ],
        'payment_failed' => [
            'label' => 'Payment fails',
            'description' => 'A gateway reports a failed payment.',
            'permission' => 'payments.view',
        ],
    ];

    private const DEFAULT_CHANNELS = ['database' => true, 'mail' => false];

    public function send(string $alert, SystemNotification $notification): void
    {
        foreach ($this->recipients($alert) as $user) {
            $channels = array_keys(array_filter(self::channelsFor($user, $alert)));

            if ($channels !== []) {
                $user->notify($notification->onChannels($channels));
            }
        }
    }

    /** @return array{database: bool, mail: bool} */
    public static function channelsFor(User $user, string $alert): array
    {
        $saved = $user->notification_preferences['admin_alerts'][$alert] ?? [];

        return [
            'database' => (bool) ($saved['database'] ?? self::DEFAULT_CHANNELS['database']),
            'mail' => (bool) ($saved['mail'] ?? self::DEFAULT_CHANNELS['mail']),
        ];
    }

    /** @return Collection<int, User> */
    private function recipients(string $alert): Collection
    {
        $permission = self::ALERTS[$alert]['permission'];

        // Filters staff in PHP because permissions support wildcards; fine for a
        // handful of admins, move the check into SQL if the staff list grows large.
        return User::with('roles.permissions')
            ->whereNull('banned_at')
            ->where(fn ($q) => $q->where('is_admin', true)->orWhereHas('roles'))
            ->get()
            ->filter(fn (User $u) => $u->hasPermission('admin.access') && $u->hasPermission($permission))
            ->values();
    }
}
