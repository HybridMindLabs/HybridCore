<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Update source
    |--------------------------------------------------------------------------
    | GitHub repository checked for new releases (owner/name). Release tags
    | are compared against the running core version (UpdateController::VERSION).
    */

    'repository' => env('HYBRIDCORE_UPDATE_REPO', 'HybridMindLabs/HybridCore'),

    /*
    |--------------------------------------------------------------------------
    | Allow updating from the admin panel
    |--------------------------------------------------------------------------
    | Disable on managed/immutable deployments where updates are rolled out
    | by CI instead of the panel.
    */

    'panel_updates' => env('HYBRIDCORE_PANEL_UPDATES', true),

    /*
    |--------------------------------------------------------------------------
    | Require signed commits for the self-update
    |--------------------------------------------------------------------------
    | Off by default — most installs have no GPG keyring set up, and forcing
    | this on would break every existing `git pull` update. When enabled,
    | every commit the update is about to merge must pass `git verify-commit`
    | against a key already in the server's keyring (`gpg --import`), so a
    | compromised GitHub account/DNS/repo can serve whatever it wants and the
    | update still refuses to apply it without the real signing key. See
    | DEPLOYMENT.md for how to import a trusted key.
    */

    'require_signed_updates' => (bool) env('HYBRIDCORE_REQUIRE_SIGNED_UPDATES', false),

    /*
    |--------------------------------------------------------------------------
    | mysqldump binary
    |--------------------------------------------------------------------------
    | Explicit path used by the admin backup tool when mysqldump is not on one
    | of the standard paths. Must be read through config so the value survives
    | `php artisan config:cache` — env() returns null once the config is cached.
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Login attempts per minute, per IP
    |--------------------------------------------------------------------------
    | Kept at the strict default. Raise it when many legitimate users share one
    | address (office NAT, CGNAT) or for end-to-end runs, which sign in far more
    | often in a minute than any real person does.
    */

    'login_rate_limit' => (int) env('LOGIN_RATE_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | API token requests per minute, per token
    |--------------------------------------------------------------------------
    | Applies to every route gated by the `abilities:` middleware. Raise it
    | for an integration that legitimately polls often.
    */

    'api_token_rate_limit' => (int) env('API_TOKEN_RATE_LIMIT', 60),
];
