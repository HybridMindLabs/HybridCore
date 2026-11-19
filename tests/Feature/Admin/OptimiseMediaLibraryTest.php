<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Page;
use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OptimiseMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function storeImage(string $path, int $width, string $ext = 'png'): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image("x.{$ext}", $width, 400)->getContent());
    }

    public function test_old_images_become_webp_and_everything_using_them_follows(): void
    {
        $this->storeImage('news/images/old-banner.png', 2400);
        $url = Storage::disk('public')->url('news/images/old-banner.png');
        $page = Page::factory()->create(['format' => 'blocks', 'blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['url' => $url]]]]);
        $rule = Rule::create(['slug' => 'r', 'title' => 'Rules', 'content' => "![banner]({$url})"]);

        $this->artisan('hybridcore:media:optimize')->assertSuccessful();

        $media = Media::firstOrFail();
        $this->assertSame('news/images/old-banner.webp', $media->path);
        $this->assertSame(1920, $media->width);
        Storage::disk('public')->assertExists('news/images/old-banner.webp');
        Storage::disk('public')->assertMissing('news/images/old-banner.png');

        $newUrl = Storage::disk('public')->url('news/images/old-banner.webp');
        $this->assertSame($newUrl, $page->fresh()->blocks[0]['data']['url']);
        $this->assertStringContainsString($newUrl, $rule->fresh()->content);
    }

    public function test_oversized_webp_is_scaled_down_in_place(): void
    {
        $this->storeImage('media/2026/01/huge.webp', 3000, 'webp');

        $this->artisan('hybridcore:media:optimize')->assertSuccessful();

        $media = Media::firstOrFail();
        $this->assertSame('media/2026/01/huge.webp', $media->path);
        $this->assertSame(1920, getimagesize(Storage::disk('public')->path($media->path))[0]);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->storeImage('news/images/old.png', 800);

        $this->artisan('hybridcore:media:optimize', ['--dry-run' => true])->expectsOutputToContain('would convert')->assertSuccessful();

        Storage::disk('public')->assertExists('news/images/old.png');
        Storage::disk('public')->assertMissing('news/images/old.webp');
    }

    public function test_an_existing_webp_with_the_same_name_is_not_overwritten(): void
    {
        $this->storeImage('news/images/logo.png', 800);
        $this->storeImage('news/images/logo.webp', 600, 'webp');
        $original = Storage::disk('public')->get('news/images/logo.webp');

        $this->artisan('hybridcore:media:optimize')->assertSuccessful();

        $this->assertSame($original, Storage::disk('public')->get('news/images/logo.webp'));
        $this->assertSame(2, Media::where('mime', 'image/webp')->count());
    }
}
