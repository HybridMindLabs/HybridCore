<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AdminNotifier;
use App\Services\CoreUpdater;
use App\Services\DatabaseBackupService;
use App\Services\Extensions\ExtensionRequirements;
use App\Services\GitUpdateVerifier;
use App\Services\UpdateLauncher;
use App\Services\UpdatePreview;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        return new FakeCoreUpdater(app(GitUpdateVerifier::class), app(DatabaseBackupService::class), app(ActivityLogService::class), app(AdminNotifier::class), app(WebhookDispatcher::class));
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

    public function test_progress_shows_the_plan_the_running_step_and_a_percentage(): void
    {
        $updater = $this->updater();
        $seen = [];

        $updater->run(onStep: function (array $log) use (&$seen) {
            if (end($log)['step'] === 'git pull') {
                $seen = Cache::get(CoreUpdater::PROGRESS_KEY);
            }
        });

        $this->assertTrue($seen['running']);
        $this->assertSame($updater->plan(), $seen['plan']);
        $this->assertGreaterThan(0, $seen['percent']);
        $this->assertLessThan(100, $seen['percent']);
        $this->assertArrayHasKey('seconds', $seen['log'][0]);

        $final = Cache::get(CoreUpdater::PROGRESS_KEY);
        $this->assertFalse($final['running']);
        $this->assertSame(100, $final['percent']);
        $this->assertNull($final['current']);
    }

    public function test_the_time_estimate_comes_from_the_previous_run(): void
    {
        $this->updater()->run();

        $eta = null;
        $this->updater()->run(onStep: function (array $log) use (&$eta) {
            if (end($log)['step'] === 'maintenance on') {
                $eta = Cache::get(CoreUpdater::PROGRESS_KEY)['eta_seconds'];
            }
        });

        $this->assertIsInt($eta);
    }

    public function test_the_plan_follows_the_options(): void
    {
        $updater = $this->updater();

        $this->assertContains('php artisan hybridcore:build', $updater->plan());
        $this->assertNotContains('php artisan hybridcore:build', $updater->plan(build: false));
        $this->assertNotContains('git pull', $updater->plan(pull: false));
        $this->assertNotContains('composer install', $updater->plan(composer: false));
    }

    public function test_the_progress_poll_still_answers_in_maintenance_mode(): void
    {
        $this->artisan('down');

        $this->getJson(route('admin.updates.status'))->assertOk();
        $this->get(route('admin.updates.index'))->assertStatus(503);
    }

    public function test_a_site_that_fails_its_health_check_is_rolled_back_with_its_old_files(): void
    {
        $updater = $this->updater();
        $updater->failing[CoreUpdater::phpBinary().' artisan hybridcore:smoke'] = true;

        $result = $updater->run();

        $this->assertFalse($result['success']);
        $this->assertTrue($result['rolled_back']);
        $this->assertContains('git reset --hard aaa1111', $updater->ran);
        // Built once for the new version, once more for the restored one.
        $this->assertCount(2, array_keys($updater->ran, 'artisan hybridcore:build', true));
    }

    public function test_every_outcome_keeps_its_log_and_alerts_the_staff(): void
    {
        $admin = auth()->user();
        $updater = $this->updater();
        $updater->failing['composer install'] = true;

        $result = $updater->run();

        $this->assertFileExists(storage_path('app/updates/'.$result['log_file']));
        $failed = ActivityLog::where('event', 'system.update_failed')->firstOrFail();
        $this->assertSame($result['log_file'], $failed->properties['log_file']);
        $this->assertSame(1, $admin->fresh()->notifications()->count());

        @unlink(storage_path('app/updates/'.$result['log_file']));
    }

    public function test_an_update_can_be_reverted_to_where_it_started(): void
    {
        $updater = $this->updater();
        $updater->headNow = 'bbb2222';

        $result = $updater->revert(['from' => 'aaa1111', 'to' => 'bbb2222', 'batch_before' => 1]);

        $this->assertTrue($result['success']);
        $this->assertSame('aaa1111', $result['to']);
        $this->assertContains('git reset --hard aaa1111', $updater->ran);
        $this->assertContains('artisan hybridcore:build', $updater->ran);
        $this->assertSame('aaa1111', ActivityLog::where('event', 'system.reverted')->firstOrFail()->properties['to']);
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_a_revert_target_that_is_not_a_commit_hash_is_refused(): void
    {
        $updater = $this->updater();

        $result = $updater->revert(['from' => '--upload-pack=touch /tmp/x', 'to' => 'bbb2222']);

        $this->assertFalse($result['success']);
        $this->assertSame([], array_filter($updater->ran, fn (string $line) => str_starts_with($line, 'git reset')));
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_the_panel_hands_the_update_to_a_background_process(): void
    {
        config(['hybridcore.panel_updates' => true, 'hybridcore.update_in_background' => true]);
        $this->app->instance(CoreUpdater::class, $this->updater());
        $this->app->instance(UpdatePreview::class, new class(app(ExtensionRequirements::class)) extends UpdatePreview
        {
            public function blockingProblems(): array
            {
                return [];
            }
        });
        $launcher = new class extends UpdateLauncher
        {
            public array $arguments = [];

            public function launch(array $arguments): bool
            {
                $this->arguments = $arguments;

                return true;
            }
        };
        $this->app->instance(UpdateLauncher::class, $launcher);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('admin.updates.apply'))
            ->assertStatus(202);

        $this->assertContains('--build', $launcher->arguments);
        $this->assertContains('--user='.auth()->id(), $launcher->arguments);
        $this->getJson(route('admin.updates.status'))->assertJsonPath('running', true)->assertJsonPath('current.step', 'starting');
    }

    public function test_a_requirement_the_new_version_has_blocks_the_update(): void
    {
        $preview = new class(app(ExtensionRequirements::class)) extends UpdatePreview
        {
            protected function git(array $arguments): ?string
            {
                return match (true) {
                    $arguments[0] === 'rev-parse' && ($arguments[1] ?? '') === '--abbrev-ref' => "main\n",
                    $arguments[0] === 'rev-parse' => "abc\n",
                    $arguments[0] === 'show' && str_ends_with($arguments[1], 'composer.json') => json_encode(['require' => ['php' => '>=99.0', 'ext-json' => '*']]),
                    $arguments[0] === 'show' => "const VERSION = '9.9.9';",
                    $arguments[0] === 'diff' && in_array('--name-only', $arguments, true) => "database/migrations/2030_01_01_000000_add_things.php\n",
                    default => ' 3 files changed',
                };
            }
        };

        $report = $preview->build();

        $this->assertSame('9.9.9', $report['version_to']);
        $this->assertSame(['2030_01_01_000000_add_things'], $report['migrations']);
        $this->assertTrue($report['blocking']);
        $this->assertStringContainsString('PHP >=99.0', $preview->blockingProblems()[0]);
    }

    public function test_only_the_latest_update_still_on_disk_can_be_rolled_back(): void
    {
        config(['hybridcore.panel_updates' => true]);
        $old = ActivityLog::create(['event' => 'system.updated', 'description' => 'old', 'properties' => ['from' => 'a', 'to' => 'b']]);
        ActivityLog::create(['event' => 'system.updated', 'description' => 'new', 'properties' => ['from' => 'b', 'to' => 'c']]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('admin.updates.revert', $old))
            ->assertStatus(409)
            ->assertJsonFragment(['Only the most recent update can be rolled back.']);
    }

    public function test_an_update_log_downloads_and_odd_names_are_refused(): void
    {
        @mkdir(storage_path('app/updates'), 0755, true);
        file_put_contents(storage_path('app/updates/update-2026-01-01-000000.json'), '{"ok":true}');
        $good = ActivityLog::create(['event' => 'system.updated', 'description' => 'x', 'properties' => ['log_file' => 'update-2026-01-01-000000.json']]);
        $bad = ActivityLog::create(['event' => 'system.updated', 'description' => 'x', 'properties' => ['log_file' => '../../.env']]);

        $this->get(route('admin.updates.log', $good))->assertOk();
        $this->get(route('admin.updates.log', $bad))->assertNotFound();

        @unlink(storage_path('app/updates/update-2026-01-01-000000.json'));
    }

    public function test_visitors_see_an_update_notice_while_it_runs(): void
    {
        Cache::put(CoreUpdater::PROGRESS_KEY, ['running' => true, 'updated_at' => now()->toIso8601String()], 60);
        $this->artisan('down');

        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('installing an update');
    }

    public function test_the_health_check_passes_on_a_working_site(): void
    {
        $this->artisan('hybridcore:smoke')->assertSuccessful()->expectsOutputToContain('The site works.');
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
