<?php

namespace App\Support;

/**
 * All named extension hooks fired by HybridCore core.
 *
 * Use these constants in extension ServiceProviders:
 *   $registry->hooks()->listen(Hooks::USER_REGISTERED, function (User $user) {
 *       // ...
 *   });
 *
 * Listeners are best-effort: a throwing listener is logged and skipped, it
 * never breaks the core action that fired the hook.
 */
final class Hooks
{
    // ── Auth / Users ─────────────────────────────────────────────────────────
    /** Fired after a new account is created. Args: (User $user) */
    public const USER_REGISTERED = 'user.registered';

    /** Fired after a user transitions to banned. Args: (User $user, ?string $reason) */
    public const USER_BANNED = 'user.banned';

    /** Fired after a successful login. Args: (User $user) */
    public const USER_LOGIN = 'user.login';

    /** Fired after a user follows another. Args: (User $follower, User $followed) */
    public const USER_FOLLOWED = 'user.followed';

    // ── Content ──────────────────────────────────────────────────────────────
    /** Fired after a news comment is posted. Args: (NewsComment $comment) */
    public const COMMENT_CREATED = 'comment.created';

    /** Fired after a server review is created or updated. Args: (ServerReview $review) */
    public const REVIEW_CREATED = 'review.created';

    /** Fired when an article transitions to published. Args: (NewsArticle $article) */
    public const ARTICLE_PUBLISHED = 'article.published';

    /** Fired after a private message is sent. Args: (Message $message) */
    public const MESSAGE_SENT = 'message.sent';

    // ── Servers ──────────────────────────────────────────────────────────────
    /** Fired after a server query completes (online or offline). Args: (Server $server, ServerSnapshot $snapshot) */
    public const SERVER_QUERIED = 'server.queried';

    // ── Extensions ───────────────────────────────────────────────────────────
    /** Fired after an extension is enabled. Args: (Extension $extension) */
    public const EXTENSION_ENABLED = 'extension.enabled';

    /** Fired after an extension is disabled. Args: (Extension $extension) */
    public const EXTENSION_DISABLED = 'extension.disabled';

    /** Fired after an extension is updated to a new version. Args: (Extension $extension, string $oldVersion) */
    public const EXTENSION_UPDATED = 'extension.updated';

    /** Fired right before an extension's files and data are removed. Args: (Extension $extension) */
    public const EXTENSION_UNINSTALLED = 'extension.uninstalled';

    private function __construct() {}
}
