<?php

namespace App\Support;

use App\Services\Extensions\Registries\HookRegistry;
use App\Services\Extensions\Registries\WebhookEventRegistry;
use App\Services\WebhookDispatcher;

/**
 * Bridges every core Hooks:: event into WebhookDispatcher, so an admin-
 * configured webhook endpoint (Admin > Webhooks) can subscribe to any of
 * them without core code changing per endpoint.
 *
 * Payloads are deliberately minimal (mostly ids) — a webhook receiver is an
 * external system, not a trusted extension, so we don't hand it full model
 * data by default. A listener that needs richer context can look the
 * record up by id using its own read access.
 */
class CoreWebhookBridge
{
    public static function register(HookRegistry $hooks): void
    {
        /** @var array<string, callable(mixed ...$args): array<string, mixed>> */
        $mappers = [
            Hooks::USER_REGISTERED => fn ($user) => ['user_id' => $user->id],
            Hooks::USER_BANNED => fn ($user, $reason = null) => ['user_id' => $user->id, 'reason' => $reason],
            Hooks::USER_LOGIN => fn ($user) => ['user_id' => $user->id],
            Hooks::USER_FOLLOWED => fn ($follower, $followed) => ['follower_id' => $follower->id, 'followed_id' => $followed->id],
            Hooks::USER_ANONYMIZED => fn ($user) => ['user_id' => $user->id],
            Hooks::COMMENT_CREATED => fn ($comment) => ['comment_id' => $comment->id],
            Hooks::REVIEW_CREATED => fn ($review) => ['review_id' => $review->id],
            Hooks::ARTICLE_PUBLISHED => fn ($article) => ['article_id' => $article->id],
            Hooks::MESSAGE_SENT => fn ($message) => ['message_id' => $message->id],
            Hooks::SERVER_QUERIED => fn ($server) => ['server_id' => $server->id],
            Hooks::EXTENSION_ENABLED => fn ($extension) => ['extension_id' => $extension->id],
            Hooks::EXTENSION_DISABLED => fn ($extension) => ['extension_id' => $extension->id],
            Hooks::EXTENSION_UPDATED => fn ($extension, $oldVersion = null) => ['extension_id' => $extension->id, 'old_version' => $oldVersion],
            Hooks::EXTENSION_UNINSTALLED => fn ($extension) => ['extension_id' => $extension->id],
        ];

        foreach ($mappers as $hook => $mapper) {
            $hooks->listen($hook, function (...$args) use ($hook, $mapper): void {
                app(WebhookDispatcher::class)->dispatch($hook, $mapper(...$args));
            });
        }
    }

    /**
     * Bridges every extension-declared WebhookEventRegistry key the same way
     * — an extension only has to register() the key and fire() it, core does
     * the dispatching. The payload is generic (no per-event mapper exists for
     * an event core doesn't know about): each hook argument becomes either its
     * ->id (models) or its raw scalar value.
     */
    public static function bridgeExtensionEvents(WebhookEventRegistry $events, HookRegistry $hooks): void
    {
        foreach (array_keys($events->all()) as $key) {
            $hooks->listen($key, function (...$args) use ($key): void {
                app(WebhookDispatcher::class)->dispatch($key, self::genericPayload($args));
            });
        }
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    private static function genericPayload(array $args): array
    {
        $payload = [];

        foreach ($args as $i => $arg) {
            $payload['arg'.$i] = match (true) {
                is_object($arg) && isset($arg->id) => $arg->id,
                is_scalar($arg) || $arg === null => $arg,
                default => null,
            };
        }

        return $payload;
    }
}
