<?php

namespace Tests\Feature;

use App\Jobs\GenerateDataExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataExportTest extends TestCase
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

    public function test_export_dispatches_queue_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->post(route('account.export'), ['password' => 'secret123'])
            ->assertRedirect();

        Queue::assertPushed(GenerateDataExportJob::class);
    }

    public function test_export_requires_correct_password(): void
    {
        Queue::fake();

        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->post(route('account.export'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        Queue::assertNotPushed(GenerateDataExportJob::class);
    }

    public function test_job_generates_file_and_notifies_user(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        (new GenerateDataExportJob($user))->handle();

        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('user_'.$user->id.'_', $files[0]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
        ]);
    }

    public function test_user_can_download_own_export_only(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();

        (new GenerateDataExportJob($owner))->handle();
        $filename = basename(Storage::disk('local')->files('exports')[0]);

        $this->actingAs($owner)
            ->get(route('account.export.download', $filename))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('account.export.download', $filename))
            ->assertForbidden();
    }
}
