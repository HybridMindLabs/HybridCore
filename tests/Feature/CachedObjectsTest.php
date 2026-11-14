<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Mail\MailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Production stores (redis, file) serialize values and the cache config
 * refuses to unserialize objects, so cached reads must survive a round trip.
 */
class CachedObjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
    }

    public function test_role_permissions_survive_a_cache_round_trip(): void
    {
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff']);
        $permission = Permission::create(['slug' => 'admin.access', 'name' => 'Access Admin Panel', 'group' => 'admin']);
        $role->permissions()->attach($permission);

        $role->permissionSlugsCached();

        $this->assertSame(['admin.access'], $role->fresh()->permissionSlugsCached()->all());
    }

    public function test_mail_templates_survive_a_cache_round_trip(): void
    {
        EmailTemplate::create(['slug' => 'welcome', 'name' => 'Welcome', 'subject' => 'Hi {{name}}', 'body_html' => '<p>Hi</p>', 'active' => true]);
        $service = app(MailTemplateService::class);

        $service->find('welcome');

        $this->assertSame('Hi Ana', $service->renderSlug('welcome', ['name' => 'Ana'])['subject']);
    }
}
