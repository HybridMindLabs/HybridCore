<?php

namespace App\Services\Extensions;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\Extension;

/**
 * Checks an extension manifest's "requires" block against the running core.
 *
 *   "requires": { "core": ">=0.1.0", "php": ">=8.3" }
 *
 * Supported constraint operators: >=, >, <=, <, =, ^ (same major, at least
 * the given version) and a bare version (treated as >=).
 */
class ExtensionRequirements
{
    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string> Human-readable errors; empty when satisfied.
     */
    public function check(array $manifest): array
    {
        $requires = $manifest['requires'] ?? [];

        if (! is_array($requires)) {
            return [];
        }

        $errors = [];

        if (is_string($requires['core'] ?? null)
            && ! $this->satisfies(UpdateController::VERSION, $requires['core'])) {
            $errors[] = "requires HybridCore {$requires['core']} — this site runs ".UpdateController::VERSION;
        }

        if (is_string($requires['php'] ?? null)
            && ! $this->satisfies(PHP_VERSION, $requires['php'])) {
            $errors[] = "requires PHP {$requires['php']} — this server runs ".PHP_VERSION;
        }

        // "requires": { "extensions": { "hybridcore/bridge": ">=1.0" } }
        foreach ((array) ($requires['extensions'] ?? []) as $slug => $constraint) {
            if (! is_string($slug) || ! is_string($constraint)) {
                continue;
            }

            $dependency = Extension::where('slug', $slug)->first();

            if ($dependency === null || ! $dependency->enabled) {
                $errors[] = "requires the \"{$slug}\" extension to be installed and enabled";

                continue;
            }

            if (! $this->satisfies($dependency->version, $constraint)) {
                $errors[] = "requires {$slug} {$constraint} — {$dependency->version} is installed";
            }
        }

        return $errors;
    }

    public function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if (preg_match('/^\^\s*(\d+)((?:\.\d+)*)$/', $constraint, $m) === 1) {
            $min = $m[1].($m[2] ?: '.0');
            $nextMajor = ((int) $m[1] + 1).'.0';

            return version_compare($version, $min, '>=')
                && version_compare($version, $nextMajor, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*([\d.]+.*)$/', $constraint, $m) === 1) {
            $operator = $m[1] !== '' ? $m[1] : '>=';
            $operator = $operator === '=' ? '==' : $operator;

            return version_compare($version, trim($m[2]), $operator);
        }

        // Unparseable constraint — don't block the admin over a typo.
        return true;
    }
}
