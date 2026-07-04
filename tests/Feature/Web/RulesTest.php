<?php

namespace Tests\Feature\Web;

use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesTest extends TestCase
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

    private function rule(array $attrs = []): Rule
    {
        return Rule::create(array_merge([
            'slug' => 'test-rule',
            'title' => 'Test Rule',
            'content' => '## Content',
            'published' => true,
            'sort_order' => 0,
        ], $attrs));
    }

    public function test_index_renders_all_published_rules(): void
    {
        $this->rule(['slug' => 'rule-1', 'title' => 'Rule One']);
        $this->rule(['slug' => 'rule-2', 'title' => 'Rule Two']);
        Rule::create(['slug' => 'draft', 'title' => 'Draft', 'content' => '', 'published' => false, 'sort_order' => 0]);

        $this->get(route('rules.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/Rules/Index')
                ->has('rules', 2)
            );
    }

    public function test_index_shows_empty_state_when_no_rules(): void
    {
        $this->get(route('rules.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/Rules/Index')
                ->has('rules', 0)
            );
    }

    public function test_show_renders_single_rule(): void
    {
        $rule = $this->rule();

        $this->get(route('rules.show', $rule->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Web/Rules/Show')
                ->where('rule.slug', $rule->slug)
                ->where('rule.title', $rule->title)
            );
    }

    public function test_show_passes_all_rules_for_sidebar(): void
    {
        $this->rule(['slug' => 'rule-a', 'title' => 'A']);
        $rule = $this->rule(['slug' => 'rule-b', 'title' => 'B']);

        $this->get(route('rules.show', $rule->slug))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('allRules', 2)
            );
    }

    public function test_show_404_for_unknown_slug(): void
    {
        $this->get(route('rules.show', 'does-not-exist'))
            ->assertNotFound();
    }

    public function test_show_404_for_unpublished_rule(): void
    {
        $rule = $this->rule(['published' => false]);

        $this->get(route('rules.show', $rule->slug))
            ->assertNotFound();
    }

    public function test_index_orders_by_sort_order(): void
    {
        $this->rule(['slug' => 'b', 'title' => 'B', 'sort_order' => 2]);
        $this->rule(['slug' => 'a', 'title' => 'A', 'sort_order' => 1]);

        $this->get(route('rules.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('rules.0.slug', 'a')
                ->where('rules.1.slug', 'b')
            );
    }
}
