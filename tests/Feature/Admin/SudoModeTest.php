<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\RequireRecentPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SudoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => Hash::make('secret-pass')]);
    }

    public function test_a_destructive_action_needs_a_recent_password(): void
    {
        $target = User::factory()->create();

        $this->withSudoExpired()
            ->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $target), [], ['X-Inertia' => 'true'])
            ->assertStatus(423);

        $this->assertModelExists($target);
    }

    public function test_a_download_redirects_to_the_confirm_page(): void
    {
        $this->withSudoExpired()
            ->actingAs($this->admin())
            ->get(route('admin.backup.export.settings'))
            ->assertRedirect(route('admin.password.confirm'));
    }

    public function test_the_right_password_unlocks_destructive_actions(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->withSudoExpired()
            ->actingAs($admin)
            ->postJson(route('admin.password.confirm.store'), ['password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['confirmed' => true]);

        $this->assertGreaterThan(0, session(RequireRecentPassword::SESSION_KEY));

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect();

        $this->assertModelMissing($target);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->withSudoExpired()
            ->actingAs($this->admin())
            ->postJson(route('admin.password.confirm.store'), ['password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame(0, session(RequireRecentPassword::SESSION_KEY));
    }

    public function test_confirmation_expires(): void
    {
        $this->withSession([RequireRecentPassword::SESSION_KEY => time() - RequireRecentPassword::TIMEOUT_SECONDS - 1])
            ->actingAs($this->admin())
            ->post(route('admin.users.bulk'), ['action' => 'ban', 'ids' => [1]], ['X-Inertia' => 'true'])
            ->assertStatus(423);
    }
}
