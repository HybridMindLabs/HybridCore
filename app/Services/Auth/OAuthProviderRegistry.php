<?php

namespace App\Services\Auth;

use App\Services\SettingsService;

/**
 * Generic OAuth provider registry. The core ships with NO providers —
 * extensions register them:
 *
 *   app(OAuthProviderRegistry::class)->register(
 *       id: 'discord',
 *       name: 'Discord',
 *       icon: 'MessageCircle',
 *       clientIdSetting: 'oauth_discord_client_id',
 *       clientSecretSetting: 'oauth_discord_client_secret',
 *       scopes: ['identify', 'email'],
 *   );
 *
 * The registry stores metadata only — client secrets stay in settings and
 * are resolved by the provider implementation at request time, never exposed
 * to the frontend.
 */
class OAuthProviderRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $providers = [];

    public function __construct(private readonly SettingsService $settings) {}

    /** @param array<int, string> $scopes */
    public function register(
        string $id,
        string $name,
        string $icon = 'Link',
        ?string $clientIdSetting = null,
        ?string $clientSecretSetting = null,
        array $scopes = [],
        ?string $redirectRoute = null,
        ?string $callbackRoute = null,
    ): void {
        $this->providers[$id] = [
            'id' => $id,
            'name' => $name,
            'icon' => $icon,
            'client_id_setting' => $clientIdSetting ?? "oauth_{$id}_client_id",
            'client_secret_setting' => $clientSecretSetting ?? "oauth_{$id}_client_secret",
            'scopes' => $scopes,
            'redirect_route' => $redirectRoute,
            'callback_route' => $callbackRoute,
        ];
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        return $this->providers[$id] ?? null;
    }

    /** Provider is registered, OAuth globally on, and the provider toggled on. */
    public function isEnabled(string $id): bool
    {
        if (! $this->has($id)) {
            return false;
        }

        if ($this->settings->get('oauth_enabled', '0') !== '1') {
            return false;
        }

        return $this->settings->get("oauth_{$id}_enabled", '0') === '1';
    }

    /**
     * Frontend-safe provider list: NO setting keys, NO secrets.
     *
     * @return array<int, array{id: string, name: string, icon: string, enabled: bool}>
     */
    public function compose(): array
    {
        return array_values(array_map(fn (array $p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'icon' => $p['icon'],
            'enabled' => $this->isEnabled($p['id']),
        ], $this->providers));
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->providers;
    }
}
