<?php

namespace Tests\Feature\Installer;

use App\Models\User;
use App\Services\InstallerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * The final step tells people that a failed install can simply be retried.
 * Anything it does therefore has to be safe to run twice: an install that dies
 * partway — unwritable .env, a dropped connection — must be able to finish on
 * the second attempt rather than being wedged for good.
 */
class InstallerRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(storage_path('installed.lock'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function installerSession(): array
    {
        return [
            'installer.database' => [
                'db_host' => '127.0.0.1',
                'db_port' => 3306,
                'db_database' => 'hybridcore_test',
                'db_username' => 'root',
                'db_password' => '',
            ],
            'installer.admin' => [
                'name' => 'HybridMind',
                'email' => 'admin@example.com',
                'password' => 'password123',
            ],
            'installer.settings' => [
                'app_name' => 'HybridCore Test',
                'app_url' => 'http://localhost',
                'app_locale' => 'en',
                'app_timezone' => 'UTC',
            ],
        ];
    }

    private function mockInstaller(): void
    {
        $this->mock(InstallerService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('writeEnvValues')->andReturnNull();
            $mock->shouldReceive('generateAppKey')->andReturnNull();
            $mock->shouldReceive('runMigrations')->andReturnNull();
        });
    }

    /**
     * The wreckage of an install that died after creating the admin account but
     * before it could finish — the real case being a write to storage/ failing
     * on the step after this one, so no lock and no installed_at were recorded.
     */
    private function leftoverAdminFromFailedAttempt(): User
    {
        return User::factory()->create([
            'name' => 'HybridMind',
            'username' => 'hybridmind',
            'email' => 'admin@example.com',
            'is_admin' => false, // the role step never got to run
        ]);
    }

    public function test_retrying_after_a_failed_install_completes(): void
    {
        $this->leftoverAdminFromFailedAttempt();
        $this->mockInstaller();

        $response = $this->withSession($this->installerSession())->post('/install/finish');

        // This used to die on the users_email_unique index: the retry generated
        // a fresh username but reused the email, so the install could never
        // finish once it had failed once.
        $response->assertRedirect('/admin');
        $this->assertFileExists(storage_path('installed.lock'));
        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_retrying_applies_the_details_from_the_second_attempt(): void
    {
        $this->leftoverAdminFromFailedAttempt();
        $this->mockInstaller();

        $corrected = $this->installerSession();
        $corrected['installer.admin']['name'] = 'Corrected Name';

        $this->withSession($corrected)->post('/install/finish');

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Corrected Name', $user->name);
        $this->assertTrue($user->is_admin, 'The retry must finish granting admin rights.');
        $this->assertSame('hybridmind', $user->username, 'The existing username should be kept, not suffixed.');
    }

    public function test_the_retried_password_is_the_one_that_works(): void
    {
        $this->leftoverAdminFromFailedAttempt();
        $this->mockInstaller();

        $this->withSession($this->installerSession())->post('/install/finish');

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(
            Hash::check('password123', $user->password),
            'The password entered on the successful attempt must be the one set.',
        );
    }

    public function test_a_second_admin_with_a_different_email_still_gets_a_unique_username(): void
    {
        User::factory()->create(['username' => 'hybridmind', 'email' => 'someone@example.com']);

        $this->mockInstaller();
        $this->withSession($this->installerSession())->post('/install/finish');

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertNotSame('hybridmind', $user->username);
        $this->assertStringStartsWith('hybridmind', $user->username);
    }

    public function test_an_unwritable_env_reports_the_problem_instead_of_a_500(): void
    {
        $this->mock(InstallerService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('writeEnvValues')
                ->andThrow(new \ErrorException('file_put_contents(/app/.env): Failed to open stream: Permission denied'));
        });

        $response = $this->withSession($this->installerSession())->post('/install/finish');

        $response->assertRedirect(route('installer.finish'));
        $response->assertSessionHasErrors('error');
        $this->assertFileDoesNotExist(storage_path('installed.lock'), 'A failed install must not lock the installer.');
    }
}
