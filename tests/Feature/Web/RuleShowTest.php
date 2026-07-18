<?php

namespace Tests\Feature\Web;

use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RuleShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function rule(string $content): Rule
    {
        return Rule::create([
            'slug' => 'general',
            'title' => 'General rules',
            'excerpt' => 'The basics.',
            'content' => $content,
            'status' => 'published',
            'sort_order' => 1,
        ]);
    }

    /** The rule text was parsed in the browser, so it needed JS to exist. */
    public function test_markdown_is_rendered_on_the_server(): void
    {
        $this->rule("## Behaviour\n\nBe **kind**.");

        $this->get(route('rules.show', 'general'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Web/Rules/Show')
                ->where('rule.content', fn (string $html) => str_contains($html, '<h2')
                    && str_contains($html, '<strong>')
                    && ! str_contains($html, '## Behaviour'))
            );
    }

    public function test_headings_produce_a_table_of_contents(): void
    {
        $this->rule("## First\n\nText.\n\n### Detail\n\nMore.");

        $this->get(route('rules.show', 'general'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('toc', 2)
                ->where('toc.0.id', 'first')
                ->where('toc.1.id', 'detail')
                ->where('toc.1.level', 3)
            );
    }

    /** Same ASCII-only slug bug the legal pages had. */
    public function test_cyrillic_headings_get_distinct_ids(): void
    {
        $this->rule("## Общи правила\n\nТекст.\n\n## Наказания\n\nТекст.");

        $this->get(route('rules.show', 'general'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $ids = array_column($page->toArray()['props']['toc'], 'id');

                $this->assertSame(['общи-правила', 'наказания'], $ids);
                $this->assertSame($ids, array_unique($ids));
            });
    }

    /** Counted from the prose; it used to include markdown syntax as words. */
    public function test_reading_time_is_computed_from_the_text(): void
    {
        $this->rule('## Heading'."\n\n".str_repeat('word ', 400));

        $this->get(route('rules.show', 'general'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reading_minutes', 2)
            );
    }

    public function test_a_rule_without_content_still_renders(): void
    {
        $this->rule('');

        $this->get(route('rules.show', 'general'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rule.content', '')
                ->has('toc', 0)
                ->where('reading_minutes', 1)
            );
    }
}
