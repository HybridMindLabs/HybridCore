<?php

namespace App\Services\Extensions;

/**
 * Validates extension.json manifests.
 *
 * Hard requirements (extension is rejected): id or slug, name, version.
 * Recommended (warnings only, kept for backwards compatibility):
 * description, author.
 *
 * Optional, type-checked when present: providers/provider, permissions,
 * settings, menu, widgets, requires, assets, translations, routes,
 * migrations, lang.
 *
 * Validation never throws and never logs manifest values that could
 * contain secrets — only field names.
 */
class ExtensionManifestValidator
{
    /**
     * @param  array<string, mixed>  $manifest
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        // Identity — "id" is the official field, "slug" the legacy alias.
        $id = $manifest['id'] ?? $manifest['slug'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            $errors[] = 'missing required field: id (or slug)';
        } elseif (! preg_match('/^[a-z0-9]+([\-\/][a-z0-9]+)*$/', $id)) {
            $errors[] = 'invalid id format (expected vendor/name or vendor-name, lowercase)';
        }

        foreach (['name', 'version'] as $field) {
            if (! is_string($manifest[$field] ?? null) || trim((string) ($manifest[$field] ?? '')) === '') {
                $errors[] = "missing required field: {$field}";
            }
        }

        if (isset($manifest['version']) && is_string($manifest['version'])
            && ! preg_match('/^\d+\.\d+(\.\d+)?([\-+][A-Za-z0-9.\-]+)?$/', $manifest['version'])) {
            $errors[] = 'invalid version format (expected semver, e.g. 1.0.0)';
        }

        foreach (['description', 'author'] as $field) {
            if (! is_string($manifest[$field] ?? null) || trim((string) ($manifest[$field] ?? '')) === '') {
                $warnings[] = "missing recommended field: {$field}";
            }
        }

        // Type checks for optional structures.
        $arrayFields = ['permissions', 'settings', 'menu', 'widgets', 'requires', 'assets', 'translations', 'routes', 'providers'];
        foreach ($arrayFields as $field) {
            if (isset($manifest[$field]) && ! is_array($manifest[$field])) {
                $errors[] = "field {$field} must be an object/array";
            }
        }

        foreach (['provider', 'migrations', 'lang', 'lang_namespace'] as $field) {
            if (isset($manifest[$field]) && ! is_string($manifest[$field])) {
                $errors[] = "field {$field} must be a string";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
