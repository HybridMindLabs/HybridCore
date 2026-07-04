<?php

namespace Tests\Feature\Web;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageTest extends TestCase
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

    public function test_published_page_is_visible(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about-us']);

        $this->get('/about-us')->assertOk();
    }

    public function test_draft_page_returns_404(): void
    {
        Page::factory()->create(['slug' => 'secret-draft']);

        $this->get('/secret-draft')->assertNotFound();
    }

    public function test_unknown_page_returns_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_sitemap_renders_published_pages(): void
    {
        Page::factory()->published()->create(['slug' => 'visible-page']);
        Page::factory()->create(['slug' => 'hidden-draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('visible-page')
            ->assertDontSee('hidden-draft');
    }

    public function test_robots_txt_disallows_admin(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap:');
    }
}
