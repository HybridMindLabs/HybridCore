<?php

namespace App\Support;

use App\Services\Extensions\Registries\NavigationRegistry;

/**
 * Core admin sidebar items. Extensions add theirs through the same registry.
 */
class CoreNavigation
{
    public static function register(NavigationRegistry $nav): void
    {
        // ── Top (no heading) ──────────────────────────────────────
        $nav->register('navigation.dashboard', 'admin.dashboard', 'LayoutDashboard', null, 'admin.access', 10, '/admin');

        // ── Management ────────────────────────────────────────────
        $nav->register('navigation.users', 'admin.users.index', 'Users', 'navigation.sections.management', 'users.view', 10);
        $nav->register('navigation.roles', 'admin.roles.index', 'ShieldCheck', 'navigation.sections.management', 'roles.view', 20);

        // ── Gaming ────────────────────────────────────────────────
        $nav->register('Servers', 'admin.servers.index', 'Server', 'navigation.sections.gaming', 'servers.view', 10);

        // ── Content ───────────────────────────────────────────────
        $nav->register('navigation.pages', 'admin.pages.index', 'FileText', 'navigation.sections.content', 'content.view', 10);
        $nav->register('navigation.menus', 'admin.menus.index', 'List', 'navigation.sections.content', 'content.view', 20);
        $nav->register('navigation.rules', 'admin.rules.index', 'BookOpen', 'navigation.sections.content', 'settings.view', 30);
        $nav->register('navigation.news_articles', 'admin.news.articles.index', 'Newspaper', 'navigation.sections.content', 'news.view', 40);
        $nav->register('navigation.news_categories', 'admin.news.categories.index', 'BarChart3', 'navigation.sections.content', 'news.view', 50);
        $nav->register('navigation.moderation', 'admin.moderation.index', 'Flag', 'navigation.sections.content', 'content.view', 55);
        $nav->register('navigation.trash', 'admin.trash.index', 'Trash2', 'navigation.sections.content', 'content.view', 58);

        // ── Communication ─────────────────────────────────────────
        $nav->register('navigation.contact', 'admin.contact.index', 'Mail', 'navigation.sections.communication', 'settings.view', 10);
        $nav->register('navigation.email', 'admin.email.settings', 'Mail', 'navigation.sections.communication', 'email.view', 20);

        // ── Analytics ─────────────────────────────────────────────
        $nav->register('navigation.analytics', 'admin.analytics.index', 'TrendingUp', 'navigation.sections.analytics', 'analytics.view', 10);
        $nav->register('navigation.activity_log', 'admin.activity-log.index', 'Activity', 'navigation.sections.analytics', 'system.view', 20);

        // ── System ────────────────────────────────────────────────
        $nav->register('navigation.settings', 'admin.settings.index', 'Settings', 'navigation.sections.system', 'settings.view', 10);
        $nav->register('navigation.system_health', 'admin.system-health.index', 'HeartPulse', 'navigation.sections.system', 'system.view', 20);
        $nav->register('navigation.updates', 'admin.updates.index', 'Download', 'navigation.sections.system', 'system.view', 30);
        $nav->register('navigation.backup', 'admin.backup.index', 'DatabaseBackup', 'navigation.sections.system', 'system.view', 40);
        $nav->register('navigation.system_logs', 'admin.system-logs.index', 'ScrollText', 'navigation.sections.system', 'system.view', 50);

        // ── Extensions ────────────────────────────────────────────
        $nav->register('navigation.extensions', 'admin.extensions.index', 'Puzzle', 'navigation.sections.extensions', 'extensions.view', 10);
        $nav->register('navigation.themes', 'admin.themes.index', 'Paintbrush', 'navigation.sections.extensions', 'themes.view', 20);
    }
}
