<?php

namespace App\Services\Extensions;

use App\Models\Extension;
use App\Models\ExtensionSetting;
use Illuminate\Support\Collection;

/**
 * Merges an extension's declared settings_schema defaults with any saved
 * ExtensionSetting overrides into one flat [key => value] array.
 *
 * Mirrors App\Services\Themes\ThemeSettingsResolver — same manifest shape,
 * same third-party-authored-metadata trust boundary.
 */
class ExtensionSettingsResolver
{
    /** @return array<int, array<string, mixed>> */
    public function schema(Extension $extension): array
    {
        $raw = $extension->metadata['settings_schema'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, function ($field) {
            if (! is_array($field) || ! isset($field['key'], $field['type'], $field['label'], $field['default'])) {
                return false;
            }

            if ($field['type'] === 'select' && empty($field['options'])) {
                return false;
            }

            return true;
        }));
    }

    /** @return array<string, mixed> */
    public function effective(Extension $extension): array
    {
        /** @var Collection<string, mixed> $values */
        $values = collect($this->schema($extension))
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['default'] ?? null]);

        if ($values->isEmpty()) {
            return [];
        }

        foreach (ExtensionSetting::where('extension_id', $extension->id)->get() as $override) {
            if ($values->has($override->key)) {
                $values[$override->key] = $override->typedValue();
            }
        }

        return $values->all();
    }
}
