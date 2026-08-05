<?php

namespace Tests\Feature\Admin;

use App\Jobs\RunScheduledBackupJob;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Update endpoint ──────────────────────────────────────────────────────

    public function test_update_schedule_requires_auth(): void
    {
        $this->put(route('admin.backup.schedule'), [
            'backup_schedule' => 'daily',
            'backup_time' => '03:00',
            'backup_retention' => 7,
        ])->assertRedirect(route('admin.login'));
    }

    public function test_update_schedule_persists_valid_values(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.backup.schedule'), [
                'backup_schedule' => 'weekly',
                'backup_time' => '04:30',
                'backup_retention' => 14,
            ])
            ->assertRedirect();

        $settings = app(SettingsService::class);
        $this->assertSame('weekly', $settings->get('backup_schedule'));
        $this->assertSame('04:30', $settings->get('backup_time'));
        $this->assertSame('14', $settings->get('backup_retention'));
    }

    public function test_update_schedule_rejects_invalid_frequency(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.backup.schedule'), [
                'backup_schedule' => 'hourly',
                'backup_time' => '03:00',
                'backup_retention' => 7,
            ])
            ->assertSessionHasErrors('backup_schedule');
    }

    public function test_update_schedule_rejects_invalid_time(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.backup.schedule'), [
                'backup_schedule' => 'daily',
                'backup_time' => 'not-a-time',
                'backup_retention' => 7,
            ])
            ->assertSessionHasErrors('backup_time');
    }

    public function test_update_schedule_rejects_retention_out_of_range(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.backup.schedule'), [
                'backup_schedule' => 'daily',
                'backup_time' => '03:00',
                'backup_retention' => 500,
            ])
            ->assertSessionHasErrors('backup_retention');
    }

    // ── RunScheduledBackupJob::isDue() ───────────────────────────────────────

    public function test_is_due_false_when_schedule_off(): void
    {
        Carbon::setTestNow('2026-08-04 03:00:00');
        app(SettingsService::class)->setMany(['backup_schedule' => 'off', 'backup_time' => '03:00']);

        $this->assertFalse(RunScheduledBackupJob::isDue());
    }

    public function test_is_due_false_outside_configured_minute(): void
    {
        Carbon::setTestNow('2026-08-04 03:05:00');
        app(SettingsService::class)->setMany(['backup_schedule' => 'daily', 'backup_time' => '03:00']);

        $this->assertFalse(RunScheduledBackupJob::isDue());
    }

    public function test_is_due_true_on_first_run(): void
    {
        Carbon::setTestNow('2026-08-04 03:00:00');
        app(SettingsService::class)->setMany(['backup_schedule' => 'daily', 'backup_time' => '03:00']);

        $this->assertTrue(RunScheduledBackupJob::isDue());
    }

    public function test_is_due_false_when_daily_already_ran_today(): void
    {
        Carbon::setTestNow('2026-08-04 03:00:00');
        app(SettingsService::class)->setMany([
            'backup_schedule' => 'daily',
            'backup_time' => '03:00',
            'backup_last_run_at' => '2026-08-04 03:00:00',
        ]);

        $this->assertFalse(RunScheduledBackupJob::isDue());
    }

    public function test_is_due_true_when_weekly_interval_elapsed(): void
    {
        Carbon::setTestNow('2026-08-04 03:00:00');
        app(SettingsService::class)->setMany([
            'backup_schedule' => 'weekly',
            'backup_time' => '03:00',
            'backup_last_run_at' => '2026-07-28 03:00:00',
        ]);

        $this->assertTrue(RunScheduledBackupJob::isDue());
    }

    public function test_is_due_false_when_weekly_interval_not_elapsed(): void
    {
        Carbon::setTestNow('2026-08-04 03:00:00');
        app(SettingsService::class)->setMany([
            'backup_schedule' => 'weekly',
            'backup_time' => '03:00',
            'backup_last_run_at' => '2026-08-01 03:00:00',
        ]);

        $this->assertFalse(RunScheduledBackupJob::isDue());
    }
}
