<?php

namespace Tests\Feature\Web;

use App\Models\LegalPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LegalPageTest extends TestCase
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

    private function page(string $content, string $slug = 'terms'): LegalPage
    {
        return LegalPage::create([
            'slug' => $slug,
            'title' => 'Terms of Service',
            'content' => $content,
            'sort_order' => 1,
        ]);
    }

    /** Markdown reached the browser raw and was parsed there with `marked`. */
    public function test_markdown_is_rendered_to_html_on_the_server(): void
    {
        $this->page("## A heading\n\nSome **bold** text.");

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Web/Legal')
                ->where('content', fn (string $html) => str_contains($html, '<h2')
                    && str_contains($html, '<strong>')
                    && ! str_contains($html, '## A heading'))
            );
    }

    public function test_headings_get_ids_and_a_table_of_contents(): void
    {
        $this->page("## First section\n\nText.\n\n### A detail\n\nMore.\n\n## Second section\n\nText.");

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('toc', 3)
                ->where('toc.0.id', 'first-section')
                ->where('toc.0.level', 2)
                ->where('toc.1.id', 'a-detail')
                ->where('toc.1.level', 3)
                ->where('toc.2.id', 'second-section')
            );
    }

    /**
     * The ids were built in JavaScript with /[^\w\s-]/, and \w is ASCII-only
     * there, so every Cyrillic heading collapsed to the same id "-" and the
     * whole contents list pointed at the first section.
     */
    public function test_cyrillic_headings_get_distinct_usable_ids(): void
    {
        $this->page("## Общи условия\n\nТекст.\n\n## Лични данни\n\nТекст.");

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $toc = $page->toArray()['props']['toc'];

                $ids = array_column($toc, 'id');

                $this->assertCount(2, $ids);
                $this->assertSame($ids, array_unique($ids), 'heading ids must be unique');
                $this->assertNotContains('-', $ids);
                $this->assertSame('общи-условия', $ids[0]);
                $this->assertSame('лични-данни', $ids[1]);
            });
    }

    /** Two sections named the same must still be separately linkable. */
    public function test_repeated_heading_text_produces_unique_ids(): void
    {
        $this->page("## Data\n\nOne.\n\n## Data\n\nTwo.");

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('toc.0.id', 'data')
                ->where('toc.1.id', 'data-2')
            );
    }

    public function test_non_ascii_content_survives_the_dom_pass(): void
    {
        $this->page("## Заглавие\n\nТова е български текст с ударения.");

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('content', fn (string $html) => str_contains($html, 'Това е български текст'))
            );
    }

    public function test_the_page_carries_a_canonical_url_and_an_iso_date(): void
    {
        $this->page('## Heading');

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canonical', fn (string $url) => str_ends_with($url, '/legal/terms'))
                ->where('updated_at', fn (string $date) => (bool) strtotime($date))
            );
    }

    public function test_an_empty_page_does_not_break(): void
    {
        $this->page('');

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('content', '')
                ->has('toc', 0)
            );
    }
}
