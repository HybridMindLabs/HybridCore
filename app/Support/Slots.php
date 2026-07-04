<?php

namespace App\Support;

/**
 * All named extension slots available in HybridCore pages.
 *
 * Use these constants in extension ServiceProviders:
 *   $registry->slots()->register(Slots::HOME_RIGHT_BOTTOM, 'MyWidget');
 *
 * Place the slot in the corresponding Vue page:
 *   <ExtensionSlot :name="Slots.HOME_RIGHT_BOTTOM" />
 */
final class Slots
{
    // ── Home ─────────────────────────────────────────────────────────────────
    /** Right column — above Featured Servers */
    public const HOME_RIGHT_TOP = 'home.right.top';

    /** Right column — below online widget (bottom of right column) */
    public const HOME_RIGHT_BOTTOM = 'home.right.bottom';

    /** Middle column — above news section */
    public const HOME_MIDDLE_TOP = 'home.middle.top';

    /** Middle column — below servers section */
    public const HOME_MIDDLE_BOTTOM = 'home.middle.bottom';

    /** Left sidebar — bottom */
    public const HOME_LEFT_BOTTOM = 'home.left.bottom';

    // ── Server ───────────────────────────────────────────────────────────────
    /** Server detail page — sidebar */
    public const SERVER_SHOW_SIDEBAR = 'server.show.sidebar';

    /** Server detail page — extra tabs (below default content) */
    public const SERVER_SHOW_TABS = 'server.show.tabs';

    /** Server list page — sidebar */
    public const SERVER_LIST_SIDEBAR = 'server.list.sidebar';

    // ── Profile ──────────────────────────────────────────────────────────────
    /** Profile page — sidebar */
    public const PROFILE_SIDEBAR = 'profile.sidebar';

    /** Profile page — extra tabs */
    public const PROFILE_TABS = 'profile.tabs';

    // ── News ─────────────────────────────────────────────────────────────────
    /** News article page — sidebar */
    public const NEWS_SHOW_SIDEBAR = 'news.show.sidebar';

    /** News article page — below content */
    public const NEWS_SHOW_BOTTOM = 'news.show.bottom';

    /** News list page — sidebar */
    public const NEWS_LIST_SIDEBAR = 'news.list.sidebar';

    // ── Account panel ─────────────────────────────────────────────────────────
    /** Account panel — below the active tab's content (receives activeTab in context) */
    public const ACCOUNT_PANEL_BOTTOM = 'account.panel.bottom';

    // ── Admin ─────────────────────────────────────────────────────────────────
    /** Admin dashboard — top (before stat cards) */
    public const ADMIN_DASHBOARD_TOP = 'admin.dashboard.top';

    /** Admin dashboard — bottom (after stat cards) */
    public const ADMIN_DASHBOARD_BOTTOM = 'admin.dashboard.bottom';

    private function __construct() {}
}
