<?php

namespace Tests\Feature\Extensions;

use App\Providers\ExtensionServiceProvider;
use Illuminate\Foundation\Application;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Stands in for a broken extension's ServiceProvider — its boot() throws,
 * simulating exactly what happened in production: a provider referencing a
 * core symbol (a constant, a class) that doesn't exist on that deploy.
 */
class ThrowingTestExtensionProvider
{
    public function __construct(Application $app) {}

    public function register(): void {}

    public function boot(): void
    {
        throw new RuntimeException('simulated broken extension');
    }
}

class ExtensionProviderBootTest extends TestCase
{
    /**
     * registerExtensionProvider() must register + boot the provider itself,
     * synchronously, rather than handing it to $this->app->register() —
     * which defers boot() to Laravel's own provider-boot loop, running
     * *after* bootEnabledExtensions()'s try/catch has already returned.
     * A throw from there was previously uncaught by anything in this
     * codebase and took the whole request down, not just the one extension.
     *
     * This asserts the throw is reachable right here, synchronously — the
     * same guarantee bootEnabledExtensions()'s try/catch around this exact
     * call already relies on for every other extension-boot step.
     */
    public function test_a_throwing_provider_boot_propagates_synchronously(): void
    {
        $esp = new ExtensionServiceProvider($this->app);
        $method = new ReflectionMethod($esp, 'registerExtensionProvider');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('simulated broken extension');

        $method->invoke($esp, base_path(), ['provider' => ThrowingTestExtensionProvider::class]);
    }
}
