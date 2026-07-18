<?php

namespace Tests\Feature\Web;

use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NewsShowTest extends TestCase
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

    private function article(string $body, string $format = 'markdown'): NewsArticle
    {
        return NewsArticle::create([
            'title' => 'Season one begins',
            'slug' => 'season-one-begins',
            'excerpt' => 'A short excerpt.',
            'body' => $body,
            'format' => $format,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * The format column was stored and validated but never acted on, so a
     * markdown article reached v-html raw and readers saw the syntax itself.
     */
    public function test_a_markdown_article_is_rendered_to_html(): void
    {
        $this->article("## A heading\n\nSome **bold** text.");

        $this->get(route('news.show', 'season-one-begins'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('article.body', fn (string $body) => str_contains($body, '<h2>')
                    && str_contains($body, '<strong>')
                    && ! str_contains($body, '## A heading'))
            );
    }

    public function test_an_html_article_is_passed_through_untouched(): void
    {
        $this->article('<p>Already <em>HTML</em>.</p>', 'html');

        $this->get(route('news.show', 'season-one-begins'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('article.body', '<p>Already <em>HTML</em>.</p>')
            );
    }

    /** The page counted words from the markup, so every tag inflated the total. */
    public function test_word_count_ignores_markup(): void
    {
        $this->article('<p>one two three four five</p>', 'html');

        $this->get(route('news.show', 'season-one-begins'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('article.word_count', 5)
            );
    }

    /** Every comment on the article used to ship in the first payload. */
    public function test_comments_are_paginated(): void
    {
        $article = $this->article('Body.', 'html');
        $author = User::factory()->create();

        foreach (range(1, 25) as $i) {
            NewsComment::create([
                'article_id' => $article->id,
                'user_id' => $author->id,
                'body' => 'Comment number '.$i,
            ]);
        }

        $this->get(route('news.show', 'season-one-begins'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('comments.data', 20)
                ->where('comments.total', 25)
                ->where('comments.last_page', 2)
            );
    }

    public function test_the_second_page_of_comments_can_be_fetched(): void
    {
        $article = $this->article('Body.', 'html');
        $author = User::factory()->create();

        foreach (range(1, 25) as $i) {
            NewsComment::create([
                'article_id' => $article->id,
                'user_id' => $author->id,
                'body' => 'Comment number '.$i,
            ]);
        }

        $this->get(route('news.show', ['article' => 'season-one-begins', 'comments_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('comments.data', 5)
                ->where('comments.current_page', 2)
            );
    }

    public function test_comments_carry_an_iso_timestamp(): void
    {
        $article = $this->article('Body.', 'html');

        NewsComment::create([
            'article_id' => $article->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'A comment.',
        ]);

        $this->get(route('news.show', 'season-one-begins'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('comments.data.0.created_at_iso', null)
            );
    }
}
