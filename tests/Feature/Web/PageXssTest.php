<?php

namespace Tests\Feature\Web;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageXssTest extends TestCase
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

    public function test_html_format_page_strips_script_tags(): void
    {
        $page = Page::factory()->published()->create([
            'format' => 'html',
            'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror="alert(1)">',
        ]);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('page.body', fn ($body) => (
                ! str_contains($body, '<script')
                && ! str_contains($body, 'onerror')
                && str_contains($body, 'Hello')
            )));
    }

    public function test_markdown_format_page_still_renders(): void
    {
        $page = Page::factory()->published()->create([
            'format' => 'markdown',
            'body' => '# Hello',
        ]);

        $this->get('/'.$page->slug)->assertOk();
    }
}
