<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_logs_activity_trait_records_model_creation_update_and_deletion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        // 1. Create
        $role = Role::create([
            'name' => 'VIP Tester',
            'slug' => 'vip-tester',
            'color' => '#10B981',
            'icon' => 'Star',
            'sort' => 10,
        ]);

        $createLog = ActivityLog::where('event', 'role.created')
            ->where('subject_id', $role->id)
            ->first();

        $this->assertNotNull($createLog);
        $this->assertSame($admin->id, $createLog->causer_id);
        $this->assertStringContainsString('VIP Tester', $createLog->description);

        // 2. Update
        $role->update(['name' => 'VIP Elite']);

        $updateLog = ActivityLog::where('event', 'role.updated')
            ->where('subject_id', $role->id)
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame('VIP Tester', $updateLog->properties['old']['name'] ?? null);
        $this->assertSame('VIP Elite', $updateLog->properties['new']['name'] ?? null);

        // 3. Delete
        $roleId = $role->id;
        $role->delete();

        $deleteLog = ActivityLog::where('event', 'role.deleted')
            ->where('subject_id', $roleId)
            ->first();

        $this->assertNotNull($deleteLog);
        $this->assertStringContainsString('VIP Elite', $deleteLog->description);
    }

    public function test_activity_log_captures_client_ip_and_user_agent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.42', 'HTTP_USER_AGENT' => 'HybridCoreTestBrowser/1.0'])
            ->post(route('admin.rules.store'), [
                'title' => 'Fair Play',
                'excerpt' => 'Play fair and respect others.',
                'content' => 'Full rule description here.',
                'sort_order' => 1,
                'published' => true,
            ]);

        $rule = Rule::where('slug', 'fair-play')->first();
        $this->assertNotNull($rule);

        $log = ActivityLog::where('subject_id', $rule->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('198.51.100.42', $log->ip_address);
        $this->assertSame('HybridCoreTestBrowser/1.0', $log->user_agent);
    }
}
