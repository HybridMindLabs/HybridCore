<?php

namespace App\Services\Webhooks;

use App\Support\Hooks;
use Illuminate\Support\Str;

class DiscordWebhookFormatter
{
    /** Check whether a target URL is a Discord webhook endpoint. */
    public static function isDiscord(string $url): bool
    {
        return str_contains($url, 'discord.com/api/webhooks')
            || str_contains($url, 'discordapp.com/api/webhooks');
    }

    /**
     * Transform a core hook event and its payload into a Discord webhook message payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function format(string $event, array $payload): array
    {
        $timestamp = now()->toIso8601String();

        return match ($event) {
            Hooks::ARTICLE_PUBLISHED => [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '📰 '.($payload['title'] ?? 'News Article Published'),
                    'description' => Str::limit((string) ($payload['summary'] ?? $payload['body'] ?? ''), 250),
                    'color' => 0x3498DB, // Blue
                    'fields' => array_values(array_filter([
                        isset($payload['author']) ? ['name' => 'Author', 'value' => (string) $payload['author'], 'inline' => true] : null,
                        isset($payload['category']) ? ['name' => 'Category', 'value' => (string) $payload['category'], 'inline' => true] : null,
                    ])),
                    'footer' => ['text' => 'HybridCore News'],
                    'timestamp' => $timestamp,
                ]],
            ],

            Hooks::SERVER_QUERIED => $this->formatServerQueried($payload, $timestamp),

            Hooks::USER_REGISTERED => [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '👋 New Member Joined',
                    'description' => sprintf(
                        'User **%s** has just registered on the platform.',
                        $payload['username'] ?? $payload['name'] ?? 'New Member'
                    ),
                    'color' => 0x9B59B6, // Purple
                    'footer' => ['text' => 'HybridCore Community'],
                    'timestamp' => $timestamp,
                ]],
            ],

            Hooks::USER_BANNED => [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '🔨 User Banned',
                    'description' => sprintf(
                        'User **%s** has been banned.',
                        $payload['username'] ?? $payload['name'] ?? 'User'
                    ),
                    'color' => 0xE74C3C, // Red
                    'fields' => isset($payload['reason'])
                        ? [['name' => 'Reason', 'value' => (string) $payload['reason'], 'inline' => false]]
                        : [],
                    'footer' => ['text' => 'HybridCore Moderation'],
                    'timestamp' => $timestamp,
                ]],
            ],

            Hooks::COMMENT_CREATED => [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '💬 New Comment Posted',
                    'description' => Str::limit((string) ($payload['body'] ?? ''), 200),
                    'color' => 0x1ABC9C, // Teal
                    'fields' => array_values(array_filter([
                        isset($payload['author']) ? ['name' => 'Author', 'value' => (string) $payload['author'], 'inline' => true] : null,
                        isset($payload['article_title']) ? ['name' => 'Article', 'value' => (string) $payload['article_title'], 'inline' => true] : null,
                    ])),
                    'footer' => ['text' => 'HybridCore Discussions'],
                    'timestamp' => $timestamp,
                ]],
            ],

            default => [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '🔔 Event: '.$event,
                    'description' => 'A new event was dispatched on HybridCore.',
                    'color' => 0x5865F2, // Discord Blurple
                    'fields' => array_slice(array_map(fn ($k, $v) => [
                        'name' => (string) $k,
                        'value' => Str::limit(is_scalar($v) ? (string) $v : (string) json_encode($v), 100),
                        'inline' => true,
                    ], array_keys($payload), array_values($payload)), 0, 10),
                    'footer' => ['text' => 'HybridCore Webhooks'],
                    'timestamp' => $timestamp,
                ]],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function formatServerQueried(array $payload, string $timestamp): array
    {
        $isOnline = (bool) ($payload['is_online'] ?? false);
        $name = (string) ($payload['name'] ?? 'Game Server');

        if (! $isOnline) {
            return [
                'username' => 'HybridCore',
                'embeds' => [[
                    'title' => '⚠️ Server Offline: '.$name,
                    'description' => (string) ($payload['failure_reason'] ?? 'Server failed to respond to query.'),
                    'color' => 0xE74C3C, // Red
                    'footer' => ['text' => 'HybridCore Server Monitor'],
                    'timestamp' => $timestamp,
                ]],
            ];
        }

        return [
            'username' => 'HybridCore',
            'embeds' => [[
                'title' => '🎮 Server Online: '.$name,
                'color' => 0x2ECC71, // Green
                'fields' => array_values(array_filter([
                    isset($payload['map']) ? ['name' => 'Map', 'value' => (string) $payload['map'], 'inline' => true] : null,
                    isset($payload['players_online'], $payload['players_max']) ? [
                        'name' => 'Players',
                        'value' => "{$payload['players_online']} / {$payload['players_max']}",
                        'inline' => true,
                    ] : null,
                    isset($payload['ping']) ? ['name' => 'Ping', 'value' => "{$payload['ping']}ms", 'inline' => true] : null,
                ])),
                'footer' => ['text' => 'HybridCore Server Monitor'],
                'timestamp' => $timestamp,
            ]],
        ];
    }
}
