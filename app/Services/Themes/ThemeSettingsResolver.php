<?php

namespace App\Services\Themes;

use App\Models\Theme;
use App\Models\ThemeSetting;
use Illuminate\Support\Collection;

/**
 * Merges a theme's declared settings_schema defaults with any saved
 * ThemeSetting overrides into one flat [key => value] array.
 */
class ThemeSettingsResolver
{
    /**
     * A theme.json is authored by a third party and only its name/slug/version
     * are validated at discovery, so settings_schema arrives in whatever shape
     * the author wrote. effective() runs on every request through
     * HandleInertiaRequests, so one malformed manifest would otherwise take
     * down the public site — drop anything that isn't a usable field instead.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schema(Theme $theme): array
    {
        $raw = $theme->metadata['settings_schema'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, function ($field) {
            if (! is_array($field) || ! isset($field['key'], $field['type'], $field['label'], $field['default'])) {
                return false;
            }

            // A select field with no (or an empty) options list can never pass
            // validation in ThemeController::settingsValidationRules() — drop it
            // rather than ship an admin field that always rejects every value.
            if ($field['type'] === 'select' && empty($field['options'])) {
                return false;
            }

            return true;
        }));
    }

    /** @return array<string, mixed> */
    public function effective(Theme $theme): array
    {
        /** @var Collection<string, mixed> $values */
        $values = collect($this->schema($theme))
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['default'] ?? null]);

        if ($values->isEmpty()) {
            return [];
        }

        foreach (ThemeSetting::where('theme_id', $theme->id)->get() as $override) {
            if ($values->has($override->key)) {
                $values[$override->key] = $override->typedValue();
            }
        }

        return $values->all();
    }
}
