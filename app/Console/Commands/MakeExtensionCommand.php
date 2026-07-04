<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffolds a new extension following the official HybridCore structure.
 *
 *   php artisan hybridcore:make-extension hybridcore/demo --with-admin --with-widget
 */
class MakeExtensionCommand extends Command
{
    protected $signature = 'hybridcore:make-extension
        {id : Extension id in vendor/name format (e.g. hybridcore/demo)}
        {--force : Overwrite an existing extension}
        {--with-admin : Include admin route, controller and Inertia page}
        {--with-web : Include public web route and controller}
        {--with-settings : Include a settings definition example}
        {--with-widget : Include a dashboard widget example}
        {--with-translations : Include en/bg translation files}
        {--with-seeder : Include a database seeder run on enable}
        {--with-schedule : Include a scheduled-tasks file}
        {--with-tests : Include test stubs}';

    protected $description = 'Scaffold a new HybridCore extension';

    private string $vendor;

    private string $extName;

    private string $studly;

    private string $studlyVendor;

    public function handle(): int
    {
        $id = strtolower((string) $this->argument('id'));

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\/[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            $this->error('Invalid extension id. Expected vendor/name (lowercase, e.g. hybridcore/demo).');

            return self::FAILURE;
        }

        [$this->vendor, $this->extName] = explode('/', $id);
        $this->studly = Str::studly($this->extName);
        $this->studlyVendor = Str::studly($this->vendor);

        $base = base_path("extensions/{$this->vendor}/{$this->extName}");

        if (is_dir($base) && ! $this->option('force')) {
            $this->error("Extension already exists at extensions/{$this->vendor}/{$this->extName}. Use --force to overwrite.");

            return self::FAILURE;
        }

        $this->createDirectories($base);
        $this->writeManifest($base, $id);
        $this->writeServiceProvider($base);
        $this->writeRoutes($base);
        $this->writeReadme($base, $id);

        if ($this->option('with-admin')) {
            $this->writeAdminController($base);
            $this->writeAdminPage($base);
        }

        if ($this->option('with-web')) {
            $this->writeWebController($base);
        }

        if ($this->option('with-translations')) {
            $this->writeTranslations($base);
        }

        if ($this->option('with-seeder')) {
            $this->writeSeeder($base);
        }

        if ($this->option('with-schedule')) {
            $this->writeSchedule($base);
        }

        if ($this->option('with-tests')) {
            $this->writeTests($base);
        }

        $this->info("Extension scaffolded at extensions/{$this->vendor}/{$this->extName}");
        $this->line('Next steps:');
        $this->line('  1. Admin → Extensions → Sync from disk');
        $this->line('  2. Enable the extension');
        $this->line("  3. php artisan hybridcore:extensions:migrate --extension={$id}");

        return self::SUCCESS;
    }

    private function createDirectories(string $base): void
    {
        $dirs = [
            'src/Http/Controllers/Admin', 'src/Http/Controllers/Web', 'src/Http/Requests',
            'src/Models', 'src/Services', 'src/Events', 'src/Listeners',
            'routes', 'database/migrations',
            'resources/js/pages/Admin', 'resources/js/pages/Web', 'resources/js/components',
            'resources/lang/en', 'resources/lang/bg', 'resources/css',
            'tests/Feature', 'tests/Unit',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir("{$base}/{$dir}")) {
                mkdir("{$base}/{$dir}", 0755, true);
            }
            // Keep empty dirs in git.
            file_put_contents("{$base}/{$dir}/.gitkeep", '');
        }
    }

    private function writeManifest(string $base, string $id): void
    {
        $manifest = [
            'id' => $id,
            'name' => $this->studly,
            'version' => '0.1.0',
            'description' => "The {$this->studly} extension.",
            'author' => $this->studlyVendor,
            'type' => 'custom',
            'provider' => "{$this->studlyVendor}\\{$this->studly}\\{$this->studly}ServiceProvider",
            'routes' => array_filter([
                'web' => $this->option('with-web') ? 'routes/web.php' : null,
                'admin' => $this->option('with-admin') ? 'routes/admin.php' : null,
            ]),
            'migrations' => 'database/migrations',
            'lang' => 'resources/lang',
            'lang_namespace' => $this->extName,
            'requires' => [
                'core' => '>='.UpdateController::VERSION,
            ],
        ];

        if ($this->option('with-seeder')) {
            $manifest['seeder'] = "{$this->studlyVendor}\\{$this->studly}\\Database\\Seeders\\DefaultSeeder";
        }

        if ($this->option('with-schedule')) {
            $manifest['schedule'] = 'routes/schedule.php';
        }

        file_put_contents(
            "{$base}/extension.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    private function writeServiceProvider(string $base): void
    {
        $widget = $this->option('with-widget') ? <<<PHP

        \$registry->widgets()->register(
            id: '{$this->extName}.example',
            title: '{$this->studly}',
            component: 'stat',
            data: fn () => ['value' => 'It works!', 'icon' => 'Puzzle', 'accent' => 'accent'],
            permission: '{$this->extName}.view',
            sort: 200,
        );
PHP : '';

        $admin = $this->option('with-admin') ? <<<PHP

        \$registry->navigation()->register(
            label: '{$this->studly}',
            route: 'admin.{$this->extName}.index',
            icon: 'Puzzle',
            section: 'Extensions',
            permission: '{$this->extName}.view',
            sort: 100,
        );
PHP : '';

        $settings = $this->option('with-settings') ? <<<PHP

        \$registry->settings()->register(
            slug: '{$this->extName}',
            label: '{$this->studly} Settings',
            route: 'admin.{$this->extName}.settings',
            permission: '{$this->extName}.manage',
        );
PHP : '';

        $content = <<<PHP
<?php

namespace {$this->studlyVendor}\\{$this->studly};

use App\\Services\\Extensions\\Registries\\ExtensionRegistry;
use Illuminate\\Support\\ServiceProvider;

class {$this->studly}ServiceProvider extends ServiceProvider
{
    public function boot(ExtensionRegistry \$registry): void
    {
        \$registry->permissions()->register('{$this->extName}.view', 'View {$this->studly}', '{$this->extName}');
        \$registry->permissions()->register('{$this->extName}.manage', 'Manage {$this->studly}', '{$this->extName}');
{$admin}{$widget}{$settings}
    }
}
PHP;

        file_put_contents("{$base}/src/{$this->studly}ServiceProvider.php", $content.PHP_EOL);
    }

    private function writeRoutes(string $base): void
    {
        if ($this->option('with-admin')) {
            $controller = "{$this->studlyVendor}\\{$this->studly}\\Http\\Controllers\\Admin\\{$this->studly}Controller";
            file_put_contents("{$base}/routes/admin.php", <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

// Loaded with web + auth + admin middleware and the /admin prefix by the core.
Route::middleware('perm:{$this->extName}.view')->group(function (): void {
    Route::get('/{$this->extName}', [\\{$controller}::class, 'index'])->name('admin.{$this->extName}.index');
});
PHP);
        }

        if ($this->option('with-web')) {
            $controller = "{$this->studlyVendor}\\{$this->studly}\\Http\\Controllers\\Web\\{$this->studly}Controller";
            file_put_contents("{$base}/routes/web.php", <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/{$this->extName}', [\\{$controller}::class, 'index'])->name('{$this->extName}.index');
PHP);
        }
    }

    private function writeAdminController(string $base): void
    {
        file_put_contents("{$base}/src/Http/Controllers/Admin/{$this->studly}Controller.php", <<<PHP
<?php

namespace {$this->studlyVendor}\\{$this->studly}\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use Inertia\\Inertia;
use Inertia\\Response;

class {$this->studly}Controller extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Extensions/{$this->vendor}/{$this->extName}/Admin/Index', [
            'message' => trans('{$this->extName}::messages.welcome'),
        ]);
    }
}
PHP);
    }

    private function writeAdminPage(string $base): void
    {
        file_put_contents("{$base}/resources/js/pages/Admin/Index.vue", <<<VUE
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { Puzzle } from '@lucide/vue';

defineProps<{ message: string }>();
</script>

<template>
    <Head title="{$this->studly}" />
    <AdminLayout title="{$this->studly}">
        <PageHeader title="{$this->studly}" :icon="Puzzle" />
        <div class="bg-[#111827] border border-[#1e2d47] rounded-xl p-5 text-[#94a3b8] text-sm">
            {{ message }}
        </div>
    </AdminLayout>
</template>
VUE);
    }

    private function writeWebController(string $base): void
    {
        file_put_contents("{$base}/src/Http/Controllers/Web/{$this->studly}Controller.php", <<<PHP
<?php

namespace {$this->studlyVendor}\\{$this->studly}\\Http\\Controllers\\Web;

use App\\Http\\Controllers\\Controller;

class {$this->studly}Controller extends Controller
{
    public function index()
    {
        return response()->json(['message' => trans('{$this->extName}::messages.welcome')]);
    }
}
PHP);
    }

    private function writeTranslations(string $base): void
    {
        file_put_contents("{$base}/resources/lang/en/messages.php", <<<PHP
<?php

return [
    'welcome' => 'Welcome to the {$this->studly} extension!',
];
PHP);
        file_put_contents("{$base}/resources/lang/bg/messages.php", <<<PHP
<?php

return [
    'welcome' => 'Добре дошли в разширението {$this->studly}!',
];
PHP);
    }

    private function writeSeeder(string $base): void
    {
        if (! is_dir("{$base}/src/Database/Seeders")) {
            mkdir("{$base}/src/Database/Seeders", 0755, true);
        }

        file_put_contents("{$base}/src/Database/Seeders/DefaultSeeder.php", <<<PHP
<?php

namespace {$this->studlyVendor}\\{$this->studly}\\Database\\Seeders;

use Illuminate\\Database\\Seeder;

/**
 * Runs on every enable — keep every insert idempotent
 * (firstOrCreate / updateOrCreate).
 */
class DefaultSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP);
    }

    private function writeSchedule(string $base): void
    {
        file_put_contents("{$base}/routes/schedule.php", <<<PHP
<?php

use Illuminate\\Support\\Facades\\Schedule;

// Loaded in console context only, and only while the extension is enabled.
// Schedule::command('{$this->extName}:example')->daily()->name('{$this->extName}-example');
PHP);
    }

    private function writeTests(string $base): void
    {
        file_put_contents("{$base}/tests/Feature/ExampleTest.php", <<<PHP
<?php

namespace {$this->studlyVendor}\\{$this->studly}\\Tests\\Feature;

use Tests\\TestCase;

class ExampleTest extends TestCase
{
    public function test_extension_translations_load(): void
    {
        app('translator')->addNamespace('{$this->extName}', base_path('extensions/{$this->vendor}/{$this->extName}/resources/lang'));

        \$this->assertNotSame('{$this->extName}::messages.welcome', trans('{$this->extName}::messages.welcome'));
    }
}
PHP);
    }

    private function writeReadme(string $base, string $id): void
    {
        file_put_contents("{$base}/README.md", <<<MD
# {$this->studly}

A HybridCore extension.

## Install

1. Admin → Extensions → Sync from disk
2. Enable **{$this->studly}**
3. `php artisan hybridcore:extensions:migrate --extension={$id}`

## Structure

See `docs/development/extensions.md` in the HybridCore repository.
MD);
    }
}
