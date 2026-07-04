<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\AdminAccountRequest;
use App\Http\Requests\Installer\AppSettingsRequest;
use App\Http\Requests\Installer\DatabaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Installer\InstallationStateService;
use App\Services\InstallerService;
use App\Services\SettingsService;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InstallerController extends Controller
{
    public function __construct(
        private readonly InstallerService $installer,
        private readonly SettingsService $settings,
    ) {}

    public function welcome(): Response
    {
        return Inertia::render('Installer/Welcome', [
            'phpVersion' => PHP_VERSION,
        ]);
    }

    public function requirements(): Response
    {
        $checks = $this->installer->checkRequirements();

        return Inertia::render('Installer/Requirements', [
            'checks' => $checks,
            'allPassed' => $this->installer->allRequirementsPassed($checks),
        ]);
    }

    public function database(Request $request): Response
    {
        return Inertia::render('Installer/Database', [
            'previous' => $request->session()->get('installer.database', [
                'db_host' => '127.0.0.1',
                'db_port' => 3306,
                'db_database' => 'hybridcore',
                'db_username' => 'root',
                'db_password' => '',
            ]),
        ]);
    }

    public function storeDatabase(DatabaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $connected = $this->installer->testDatabaseConnection(
            $data['db_host'],
            (int) $data['db_port'],
            $data['db_database'],
            $data['db_username'],
            $data['db_password'] ?? '',
        );

        if (! $connected) {
            return back()->withErrors([
                'db_host' => 'Could not connect to the database. Please check the credentials and try again.',
            ])->withInput();
        }

        $request->session()->put('installer.database', $data);

        return redirect()->route('installer.admin');
    }

    public function adminAccount(Request $request): Response
    {
        return Inertia::render('Installer/AdminAccount', [
            'previous' => $request->session()->get('installer.admin', [
                'name' => '',
                'email' => '',
            ]),
        ]);
    }

    public function storeAdminAccount(AdminAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->session()->put('installer.admin', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return redirect()->route('installer.settings');
    }

    public function settings(Request $request): Response
    {
        return Inertia::render('Installer/AppSettings', [
            'previous' => $request->session()->get('installer.settings', [
                'app_name' => 'HybridCore',
                'app_url' => config('app.url', 'http://localhost'),
                'app_locale' => 'en',
                'app_timezone' => 'UTC',
            ]),
            'locales' => ['en' => 'English', 'bg' => 'Bulgarian', 'de' => 'German', 'fr' => 'French', 'pl' => 'Polish'],
            'timezones' => $this->getTimezoneList(),
        ]);
    }

    public function storeSettings(AppSettingsRequest $request): RedirectResponse
    {
        $request->session()->put('installer.settings', $request->validated());

        return redirect()->route('installer.finish');
    }

    public function finish(Request $request): Response
    {
        $hasDatabase = $request->session()->has('installer.database');
        $hasAdmin = $request->session()->has('installer.admin');
        $hasSettings = $request->session()->has('installer.settings');

        return Inertia::render('Installer/Finish', [
            'ready' => $hasDatabase && $hasAdmin && $hasSettings,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $database = $request->session()->get('installer.database');
        $admin = $request->session()->get('installer.admin');
        $settings = $request->session()->get('installer.settings');

        if (! $database || ! $admin || ! $settings) {
            return redirect()->route('installer.welcome')
                ->withErrors(['error' => 'Installation data is incomplete. Please restart the installer.']);
        }

        // 1. Write environment values
        $this->installer->writeEnvValues([
            'APP_NAME' => $settings['app_name'],
            'APP_URL' => $settings['app_url'],
            'APP_LOCALE' => $settings['app_locale'],
            'APP_TIMEZONE' => $settings['app_timezone'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['db_host'],
            'DB_PORT' => $database['db_port'],
            'DB_DATABASE' => $database['db_database'],
            'DB_USERNAME' => $database['db_username'],
            'DB_PASSWORD' => $database['db_password'] ?? '',
        ]);

        // 2. Reconfigure the default mysql connection at runtime so migrations
        //    run against the correct DB. Purge first so Laravel discards any
        //    previously-resolved PDO instance and picks up the new config.
        Config::set('database.connections.mysql.host', $database['db_host']);
        Config::set('database.connections.mysql.port', (int) $database['db_port']);
        Config::set('database.connections.mysql.database', $database['db_database']);
        Config::set('database.connections.mysql.username', $database['db_username']);
        Config::set('database.connections.mysql.password', $database['db_password'] ?? '');
        DB::purge('mysql');
        DB::reconnect('mysql');

        // 3. Generate APP_KEY if not already set
        $this->installer->generateAppKey();

        // 4. Run migrations. On failure: log a SAFE error (no credentials)
        //    and send the user back without creating the lock file, so the
        //    installer can be retried.
        try {
            $this->installer->runMigrations();
        } catch (\Throwable $e) {
            Log::error('Installer: migrations failed', [
                'exception' => $e::class,
                // Message may reference table names but never credentials.
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('installer.finish')
                ->withErrors(['error' => 'Database migration failed. Check the database credentials and database server logs, then try again.']);
        }

        // 5. Create the first admin user and log them in
        $adminBase = preg_replace('/[^a-z0-9_-]/i', '', strtolower(explode(' ', $admin['name'])[0])) ?: 'admin';
        $adminUsername = $adminBase;
        $ai = 2;
        while (User::where('username', $adminUsername)->exists()) {
            $adminUsername = $adminBase.'_'.$ai++;
        }

        $user = User::create([
            'name' => $admin['name'],
            'username' => $adminUsername,
            'email' => $admin['email'],
            'password' => Hash::make($admin['password']),
            'is_admin' => true,
        ]);

        Auth::login($user);

        // 6. Seed core permissions and base roles, attach admin role
        (new CorePermissionsSeeder)->run();
        $adminRole = Role::where('slug', 'administrator')->first();
        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // 7. Seed initial settings into the settings table
        $this->settings->setMany([
            'app_name' => $settings['app_name'],
            'app_url' => $settings['app_url'],
            'default_locale' => $settings['app_locale'],
            'timezone' => $settings['app_timezone'],
            'maintenance_mode' => '0',
            'active_theme' => 'hybridcore/default',
        ]);

        // 7. Write ALL installation markers (lock file, APP_INSTALLED, settings.installed_at)
        app(InstallationStateService::class)->markInstalled([
            'source' => 'installer',
        ]);

        // 8. Clear cached config so the new .env values (incl. APP_INSTALLED) apply
        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // Non-fatal — markers are already written.
        }

        // 9. Clear session installer data
        $request->session()->forget(['installer.database', 'installer.admin', 'installer.settings']);

        return redirect()->route('admin.dashboard')
            ->with('success', 'HybridCore has been installed successfully. Welcome!');
    }

    private function getTimezoneList(): array
    {
        $zones = [];
        foreach (timezone_identifiers_list() as $tz) {
            $zones[$tz] = $tz;
        }

        return $zones;
    }
}
