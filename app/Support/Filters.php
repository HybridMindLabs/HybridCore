<?php

namespace App\Support;

/**
 * All named value filters applied by HybridCore core.
 *
 * Unlike hooks (which notify), filters transform: every registered callback
 * receives the value and must return it (possibly modified).
 *
 *   $registry->filters()->add(Filters::INERTIA_SHARED, fn (array $shared) => [...]);
 *
 * Extensions may also define their own filter names (prefix them with the
 * extension slug) to open integration points for other extensions.
 */
final class Filters
{
    /** Shared Inertia props for every page. Value: array. Args: (Request $request) */
    public const INERTIA_SHARED = 'inertia.shared';

    /** Props for the public server detail page. Value: array. Args: (Server $server) */
    public const SERVER_SHOW_PROPS = 'server.show.props';

    /** Props for the public profile page. Value: array. Args: (User $user) */
    public const PROFILE_SHOW_PROPS = 'profile.show.props';

    private function __construct() {}
}
