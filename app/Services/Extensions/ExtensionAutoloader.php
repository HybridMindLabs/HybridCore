<?php

namespace App\Services\Extensions;

/**
 * PSR-4 autoloader for an extension's src/ directory so extensions work
 * without composer.json edits. Namespace comes from the manifest
 * "namespace" key or is derived from the provider class FQCN.
 */
class ExtensionAutoloader
{
    /** @var array<string, true> Prefixes already registered this request. */
    private static array $registered = [];

    /** @param array<string, mixed> $manifest */
    public static function register(string $base, array $manifest): void
    {
        $namespace = $manifest['namespace'] ?? null;

        if (! is_string($namespace) && is_string($manifest['provider'] ?? null)) {
            // "Vendor\Name\NameServiceProvider" -> "Vendor\Name"
            $pos = strrpos($manifest['provider'], '\\');
            $namespace = $pos !== false ? substr($manifest['provider'], 0, $pos) : null;
        }

        if (! is_string($namespace) || $namespace === '' || ! is_dir($base.'/src')) {
            return;
        }

        $prefix = rtrim($namespace, '\\').'\\';

        if (isset(self::$registered[$prefix])) {
            return;
        }

        self::$registered[$prefix] = true;
        $srcDir = $base.'/src/';

        spl_autoload_register(function (string $class) use ($prefix, $srcDir): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $srcDir.$relative.'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
