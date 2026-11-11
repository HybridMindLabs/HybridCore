<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\CoreUpdater;
use App\Services\DatabaseBackupService;
use App\Services\GitUpdateVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Stands in for git/composer/artisan so the update flow can be driven step by step. */
class FakeCoreUpdater extends CoreUpdater
{
    /** @var array<int, string> */
    public array $ran = [];

    public string $headNow = 'aaa1111';

    /** @var array<string, true> step => fail */
    public array $failing = [];

    public string $dirty = '';

    protected function head(): string
    {
        return $this->headNow;
    }

    protected function exec(array $command): array
    {
        $line = implode(' ', $command);
        $this->ran[] = $line;

        if ($line === 'git status --porcelain --untracked-files=no') {
            return [true, $this->dirty];
        }
        if ($line === 'git pull --ff-only') {
            $this->headNow = 'bbb2222';
        }
        if (str_starts_with($line, 'git reset --hard ')) {
            $this->headNow = substr($line, strlen('git reset --hard '));
        }

        foreach (array_keys($this->failing) as $needle) {
            if (str_starts_with($line, $needle)) {
                unset($this->failing[$needle]); // fail once, like a flaky network

                return [false, "{$needle} exploded"];
            }
        }

        return [true, ''];
    }

    protected function artisanStep(string $command, array $arguments = []): void
    {
        $this->ran[] = "artisan {$command}";

        if ($command === 'migrate' && isset($this->failing['migrate'])) {
            // A migration that ran before the failing one lands in a new batch.
            DB::table('migrations')->insert(['migration' => 'partial_'.uniqid(), 'batch' => ((int) DB::table('migrations')->max('batch')) + 1]);
            throw new RuntimeException('php artisan migrate failed.');
        }
        if (isset($this->failing[$command]) && $command !== 'migrate') {
            throw new RuntimeException("php artisan {$command} failed.");
        }
    }
}

class CoreUpdaterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        @unlink(storage_path('framework/down'));
        parent::tearDown();
    }

    private function updater(): FakeCoreUpdater
    {
        return new FakeCoreUpdater(app(GitUpdateVerifier::class), app(DatabaseBackupService::class), app(ActivityLogService::class));
    }

    public function test_preflight_blocks_local_changes_to_tracked_files(): void
    {
        $updater = $this->updater();
        $updater->dirty = " M app/Models/User.php\n";

        $this->assertStringContainsString('app/Models/User.php', implode(' ', $updater->preflight()));
    }

    public function test_a_clean_update_runs_every_step_and_is_recorded(): void
    {
        $updater = $this->updater();

        $result = $updater->run();

        $this->assertTrue($result['success']);
        $this->assertSame(['aaa1111', 'bbb2222'], [$result['from'], $result['to']]);
        $this->assertContains('artisan migrate', $updater->ran);
        $this->assertContains('artisan hybridcore:build', $updater->ran);
        $this->assertNotContains('git reset --hard aaa1111', $updater->ran);
        $this->assertSame('bbb2222', ActivityLog::where('event', 'system.updated')->firstOrFail()->properties['to']);
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_a_failed_composer_install_restores_the_previous_code(): void
    {
        $updater = $this->updater();
        $updater->failing['composer install'] = true;

        $result = $updater->run();

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rolled_back']);
        $this->assertContains('git reset --hard aaa1111', $updater->ran);
        $this->assertSame('aaa1111', $result['to']);
        $this->assertNotContains('artisan migrate', $updater->ran);
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_a_failed_migration_rolls_back_its_batch_before_the_code(): void
    {
        $updater = $this->updater();
        $updater->failing['migrate'] = true;

        $result = $updater->run();

        $this->assertFalse($result['success']);
        $rollback = array_search('artisan migrate:rollback', $updater->ran, true);
        $reset = array_search('git reset --hard aaa1111', $updater->ran, true);
        $this->assertNotFalse($rollback);
        $this->assertNotFalse($reset);
        $this->assertLessThan($reset, $rollback, 'Migrations must roll back while the new code (with their down()) is still checked out.');
    }

    public function test_a_failure_before_the_pull_changes_nothing(): void
    {
        $updater = $this->updater();
        $updater->failing['git fetch'] = true;

        $result = $updater->run();

        $this->assertFalse($result['success']);
        $this->assertFalse($result['rolled_back']);
        $this->assertNotContains('git pull --ff-only', $updater->ran);
        $this->assertNotContains('git reset --hard aaa1111', $updater->ran);
    }

    public function test_the_panel_refuses_to_start_when_preflight_fails(): void
    {
        config(['hybridcore.panel_updates' => true]);
        $updater = $this->updater();
        $updater->dirty = " M routes/web.php\n";
        $this->app->instance(CoreUpdater::class, $updater);

        $this->postJson(route('admin.updates.apply'))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['problems' => [$updater->preflight()[0]]]);

        $this->assertSame([], array_filter($updater->ran, fn ($l) => str_starts_with($l, 'git pull')));
    }

    public function test_status_reports_progress_of_the_last_run(): void
    {
        $this->updater()->run();

        $this->getJson(route('admin.updates.status'))
            ->assertOk()
            ->assertJsonPath('running', false)
            ->assertJsonPath('result.success', true);
    }
}
