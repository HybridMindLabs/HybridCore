<?php

namespace Tests\Feature\Extensions;

use App\Models\Extension;
use App\Services\Extensions\ExtensionUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Update discovery decides whether the panel offers to fetch and execute code
 * from a URL, so the rules about what it will and will not accept are the part
 * worth pinning down.
 */
class ExtensionUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function extension(array $metadata = [], string $version = '1.0.0'): Extension
    {
        return Extension::create([
            'name' => 'Demo',
            'slug' => 'demo',
            'version' => $version,
            'author' => 'Someone',
            'description' => '',
            'type' => 'community',
            'path' => 'hybridcore/demo',
            'enabled' => false,
            'metadata' => $metadata,
        ]);
    }

    private function feed(array $overrides = []): array
    {
        return array_merge([
            'version' => '2.0.0',
            'download_url' => 'https://example.test/demo-2.0.0.zip',
            'notes' => 'Fixes things.',
        ], $overrides);
    }

    private function service(): ExtensionUpdateService
    {
        return app(ExtensionUpdateService::class);
    }

    public function test_a_newer_version_in_the_feed_is_reported(): void
    {
        Http::fake(['feed.test/*' => Http::response($this->feed())]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $release = $this->service()->check($ext);

        $this->assertSame('2.0.0', $release['version']);
        $this->assertSame('https://example.test/demo-2.0.0.zip', $release['download_url']);
    }

    public function test_the_same_or_an_older_version_is_not_an_update(): void
    {
        Http::fake(['feed.test/*' => Http::response($this->feed(['version' => '1.0.0']))]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $this->assertNull($this->service()->check($ext));
    }

    public function test_an_extension_without_a_feed_is_never_checked(): void
    {
        Http::fake();
        $this->assertNull($this->service()->check($this->extension()));

        Http::assertNothingSent();
    }

    public function test_a_plain_http_feed_url_is_refused(): void
    {
        Http::fake();
        // Update metadata over http is attacker-controllable in transit.
        $ext = $this->extension(['update_url' => 'http://feed.test/demo.json']);

        $this->assertNull($this->service()->check($ext));
        Http::assertNothingSent();
    }

    public function test_a_feed_offering_a_plain_http_download_is_refused(): void
    {
        Http::fake([
            'feed.test/*' => Http::response($this->feed([
                'download_url' => 'http://example.test/demo-2.0.0.zip',
            ])),
        ]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        // The download becomes executable code — it must not be interceptable.
        $this->assertNull($this->service()->check($ext));
    }

    public function test_an_unreachable_or_malformed_feed_yields_no_update(): void
    {
        Http::fake(['feed.test/*' => Http::response('not json at all', 200)]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $this->assertNull($this->service()->check($ext));
    }

    public function test_check_all_only_returns_extensions_with_a_newer_release(): void
    {
        Http::fake([
            'feed.test/current*' => Http::response($this->feed(['version' => '1.0.0'])),
            'feed.test/*' => Http::response($this->feed()),
        ]);

        $this->extension(['update_url' => 'https://feed.test/new.json']);
        Extension::create([
            'name' => 'Current', 'slug' => 'current', 'version' => '1.0.0',
            'author' => 'A', 'description' => '', 'type' => 'community',
            'path' => 'hybridcore/current', 'enabled' => false,
            'metadata' => ['update_url' => 'https://feed.test/current.json'],
        ]);

        $available = $this->service()->checkAll();

        $this->assertArrayHasKey('demo', $available);
        $this->assertArrayNotHasKey('current', $available);
    }

    public function test_results_are_cached_so_the_page_does_not_poll_every_author(): void
    {
        Http::fake(['feed.test/*' => Http::response($this->feed())]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $this->service()->check($ext);
        $this->service()->check($ext);

        Http::assertSentCount(1);
    }

    public function test_a_fresh_check_bypasses_the_cache(): void
    {
        Http::fake(['feed.test/*' => Http::response($this->feed())]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $this->service()->check($ext);
        $this->service()->check($ext, fresh: true);

        Http::assertSentCount(2);
    }

    public function test_applying_without_an_available_update_fails_loudly(): void
    {
        Http::fake(['feed.test/*' => Http::response($this->feed(['version' => '1.0.0']))]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        $this->expectException(\RuntimeException::class);

        $this->service()->apply($ext);
    }

    public function test_a_failed_download_does_not_leave_a_staged_archive_behind(): void
    {
        Http::fake([
            'feed.test/*' => Http::response($this->feed()),
            'example.test/*' => Http::response('nope', 404),
        ]);
        $ext = $this->extension(['update_url' => 'https://feed.test/demo.json']);

        try {
            $this->service()->apply($ext);
            $this->fail('A 404 download should not be treated as a successful update.');
        } catch (\RuntimeException) {
            // expected
        }

        $staged = glob(storage_path('app/extension-imports/*.zip')) ?: [];
        $this->assertSame([], $staged);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
