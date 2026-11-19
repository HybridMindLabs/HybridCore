<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use App\Services\Extensions\Registries\PageBlockRegistry;
use App\Support\CorePageBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBlocksTest extends TestCase
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

        parent::tearDown();
    }

    private function registry(): PageBlockRegistry
    {
        return app(PageBlockRegistry::class);
    }

    public function test_saved_blocks_keep_only_declared_fields_and_safe_links(): void
    {
        $clean = $this->registry()->sanitize([
            ['id' => 'a1', 'type' => 'hero', 'data' => [
                'title' => 'Welcome',
                'injected' => 'dropped',
                'image' => 'javascript:alert(1)',
                'align' => 'sideways',
                'buttons' => [
                    ['label' => 'Join', 'url' => '//evil.example/x', 'style' => 'primary'],
                    ['label' => 'Rules', 'url' => '/rules', 'style' => 'secondary'],
                ],
            ]],
            ['type' => 'no-such-block', 'data' => []],
            ['id' => 'bad id!', 'type' => 'news', 'data' => ['limit' => 500]],
        ]);

        $this->assertCount(2, $clean);
        $hero = $clean[0]['data'];
        $this->assertSame('Welcome', $hero['title']);
        $this->assertArrayNotHasKey('injected', $hero);
        $this->assertSame('', $hero['image']);
        $this->assertSame('left', $hero['align']);
        $this->assertSame('', $hero['buttons'][0]['url']);
        $this->assertSame('/rules', $hero['buttons'][1]['url']);

        $this->assertNotSame('bad id!', $clean[1]['id']);
        $this->assertSame(12, $clean[1]['data']['limit']);
    }

    public function test_a_block_page_renders_its_blocks_with_markdown_made_safe(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.store'), [
            'title' => 'Join us',
            'format' => 'blocks',
            'status' => 'published',
            'blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'Play with us']],
                ['type' => 'text', 'data' => ['body' => '**Rules** apply <script>alert(1)</script> [x](javascript:alert(1))']],
            ],
        ])->assertSessionHasNoErrors();

        $this->get('/join-us')->assertOk()->assertInertia(fn ($page) => $page
            ->where('page.format', 'blocks')
            ->where('page.blocks.0.component', 'PageBlockHero')
            ->where('page.blocks.0.data.title', 'Play with us')
            ->where('page.blocks.1.props.html', fn (string $html) => str_contains($html, '<strong>Rules</strong>')
                && ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
    }

    public function test_a_page_scheduled_for_later_stays_hidden_until_then(): void
    {
        Page::factory()->create(['slug' => 'launch', 'status' => 'published', 'published_at' => now()->addDay()]);

        $this->get('/launch')->assertNotFound();

        $this->travel(2)->days();
        $this->get('/launch')->assertOk();
    }

    public function test_saving_keeps_the_previous_version_and_it_can_be_restored(): void
    {
        $page = Page::factory()->create(['title' => 'First', 'format' => 'markdown', 'body' => 'one', 'status' => 'draft']);

        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
            'title' => 'Second', 'slug' => $page->slug, 'body' => 'two', 'format' => 'markdown', 'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $revision = $page->revisions()->firstOrFail();
        $this->assertSame('First', $revision->title);
        $this->assertSame('one', $revision->body);

        $this->actingAs($this->admin)->post(route('admin.pages.revisions.restore', [$page, $revision]))->assertRedirect();

        $this->assertSame('First', $page->fresh()->title);
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_only_the_newest_revisions_are_kept(): void
    {
        $page = Page::factory()->create();

        for ($i = 0; $i < Page::MAX_REVISIONS + 5; $i++) {
            $page->saveRevision($this->admin);
        }

        $this->assertSame(Page::MAX_REVISIONS, $page->revisions()->count());
    }

    public function test_preview_renders_unsaved_blocks(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.pages.preview'), ['blocks' => [
                ['type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ]])
            ->assertOk()
            ->assertJsonPath('blocks.0.props.embed', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_links_become_embeds_and_other_links_do_not(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', CorePageBlocks::youtubeEmbed('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', CorePageBlocks::youtubeEmbed('https://youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertNull(CorePageBlocks::youtubeEmbed('https://evil.example/watch?v=dQw4w9WgXcQ'));
    }

    public function test_extensions_can_add_blocks_and_a_failing_block_is_skipped(): void
    {
        $this->registry()->register('acme.hello', 'Hello', 'AcmeHelloBlock', [
            ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
        ], fn (array $d) => ['greeting' => "Hello {$d['name']}"]);
        $this->registry()->register('acme.broken', 'Broken', 'AcmeBroken', [], fn () => throw new \RuntimeException('boom'));

        $rendered = $this->registry()->render([
            ['type' => 'acme.broken', 'data' => []],
            ['type' => 'acme.hello', 'data' => ['name' => 'Arishem']],
        ]);

        $this->assertCount(1, $rendered);
        $this->assertSame('Hello Arishem', $rendered[0]['props']['greeting']);
    }

    public function test_blocks_of_a_disabled_extension_survive_an_edit_but_stay_hidden(): void
    {
        $page = Page::factory()->create(['slug' => 'shop-promo', 'format' => 'blocks', 'status' => 'published', 'published_at' => now(), 'blocks' => [
            ['id' => 'h1', 'type' => 'hero', 'data' => ['title' => 'Sale']],
            ['id' => 'p1', 'type' => 'acme.products', 'data' => ['limit' => 4]],
        ]]);

        $this->actingAs($this->admin)->get(route('admin.pages.edit', $page))
            ->assertInertia(fn ($p) => $p->has('page.blocks', 2)->where('page.blocks.1.type', 'acme.products'));

        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
            'title' => 'Promo', 'slug' => 'shop-promo', 'format' => 'blocks', 'status' => 'published',
            'blocks' => $page->blocks,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['limit' => 4], $page->fresh()->blocks[1]['data']);
        $this->get('/shop-promo')->assertInertia(fn ($p) => $p->has('page.blocks', 1));
    }

    public function test_block_images_upload_as_webp(): void
    {
        Storage::fake('public');

        $url = $this->actingAs($this->admin)
            ->post(route('admin.pages.images.upload'), ['file' => UploadedFile::fake()->image('banner.png', 2400, 1200)])
            ->assertOk()
            ->json('url');

        $this->assertStringContainsString('/media/', $url);
        $this->assertStringEndsWith('.webp', $url);
        $this->assertCount(1, Storage::disk('public')->files('media/'.now()->format('Y/m')));
    }

    public function test_the_editor_receives_the_block_catalogue(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pages.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('blockTypes', fn ($types) => $types->each(fn ($t) => $t->hasAll(['type', 'label', 'fields', 'defaults'])->etc()))
                ->has('games'));
    }
}
