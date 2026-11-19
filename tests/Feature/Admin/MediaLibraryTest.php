<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    private function upload(string $folder = ''): Media
    {
        $this->actingAs($this->admin)
            ->post(route('admin.media.store'), [
                'files' => [UploadedFile::fake()->image('Server Banner.png', 2400, 1200)],
                'folder' => $folder,
            ])
            ->assertOk();

        return Media::latest('id')->firstOrFail();
    }

    public function test_uploads_are_resized_to_webp_and_recorded(): void
    {
        $media = $this->upload('  Banners  ');

        $this->assertSame('Server Banner', $media->name);
        $this->assertSame('Banners', $media->folder);
        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertSame(1920, $media->width);
        Storage::disk('public')->assertExists($media->path);

        // The grid loads a small copy, not the 1920px original.
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(480, getimagesize(Storage::disk('public')->path($media->thumb_path))[0]);
        $this->actingAs($this->admin)->getJson(route('admin.media.browse'))->assertJsonPath('media.data.0.thumb', $media->thumb_url);
    }

    public function test_thumbnails_are_neither_listed_nor_left_behind(): void
    {
        $media = $this->upload();

        $this->actingAs($this->admin)->post(route('admin.media.scan'));
        $this->assertSame(1, Media::count());

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $media));
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    public function test_browse_filters_by_search_and_folder(): void
    {
        $this->upload('Banners');
        Media::create(['path' => 'media/x.webp', 'name' => 'Logo', 'mime' => 'image/webp', 'size' => 10]);

        $this->actingAs($this->admin)->getJson(route('admin.media.browse', ['search' => 'logo']))
            ->assertJsonCount(1, 'media.data')->assertJsonPath('media.data.0.name', 'Logo');
        $this->actingAs($this->admin)->getJson(route('admin.media.browse', ['folder' => '__none']))
            ->assertJsonCount(1, 'media.data');
        $this->actingAs($this->admin)->getJson(route('admin.media.browse'))
            ->assertJsonPath('folders', ['Banners']);
    }

    public function test_an_image_in_use_is_not_deleted_without_force(): void
    {
        $media = $this->upload();
        $page = Page::factory()->create(['format' => 'blocks', 'blocks' => [
            ['id' => 'a', 'type' => 'image', 'data' => ['url' => $media->url]],
        ]]);

        $this->actingAs($this->admin)->getJson(route('admin.media.usages', $media))
            ->assertJsonPath('usages.0.title', $page->title);

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $media))->assertSessionHas('error');
        $this->assertModelExists($media);

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $media), ['force' => 1])->assertSessionHas('success');
        $this->assertModelMissing($media);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_scan_adds_images_that_were_uploaded_before_the_library(): void
    {
        $disk = Storage::disk('public');
        $disk->put('news/images/old.webp', UploadedFile::fake()->image('old.webp')->getContent());
        $disk->put('pages/images/hero.png', UploadedFile::fake()->image('hero.png')->getContent());
        $disk->put('news/images/notes.txt', 'not an image');

        $this->actingAs($this->admin)->post(route('admin.media.scan'))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.media.scan'));

        $this->assertSame(2, Media::count());
        $this->assertSame('News', Media::where('path', 'news/images/old.webp')->value('folder'));
    }

    public function test_alt_text_and_folder_can_be_edited(): void
    {
        $media = $this->upload();

        $this->actingAs($this->admin)->put(route('admin.media.update', $media), ['name' => 'Hero', 'alt' => 'Players on de_dust2', 'folder' => 'Maps'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Players on de_dust2', $media->fresh()->alt);
        $this->assertSame('Maps', $media->fresh()->folder);
    }

    public function test_members_without_content_access_cannot_upload(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('x.png')]])
            ->assertRedirect();

        $this->assertSame(0, Media::count());
    }
}
