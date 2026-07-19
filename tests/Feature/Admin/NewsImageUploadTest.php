<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Media\NewsImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class NewsImageUploadTest extends TestCase
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

    public function test_upload_is_re_encoded_to_webp(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.news.media.upload'), [
                'file' => UploadedFile::fake()->image('screenshot.png', 800, 600),
            ]);

        $response->assertOk();

        $path = $response->json('path');

        $this->assertStringEndsWith('.webp', $path, 'the stored file should be WebP whatever was uploaded');
        Storage::disk('public')->assertExists($path);
    }

    public function test_oversized_upload_is_capped_in_width(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->postJson(route('admin.news.media.upload'), [
                'file' => UploadedFile::fake()->image('huge.png', 4000, 2000),
            ])
            ->assertOk();

        $path = collect(Storage::disk('public')->files(NewsImageService::DIRECTORY))->first();

        $image = (new ImageManager(new Driver))
            ->read(Storage::disk('public')->get($path));

        $this->assertSame(NewsImageService::MAX_WIDTH, $image->width());
        // Aspect ratio has to survive the downscale — 4000x2000 is 2:1.
        $this->assertSame(NewsImageService::MAX_WIDTH / 2, $image->height());
    }

    public function test_image_smaller_than_the_cap_is_not_upscaled(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->postJson(route('admin.news.media.upload'), [
                'file' => UploadedFile::fake()->image('small.png', 400, 300),
            ])
            ->assertOk();

        $path = collect(Storage::disk('public')->files(NewsImageService::DIRECTORY))->first();

        $image = (new ImageManager(new Driver))
            ->read(Storage::disk('public')->get($path));

        $this->assertSame(400, $image->width());
    }

    public function test_guests_cannot_upload(): void
    {
        Storage::fake('public');

        $this->postJson(route('admin.news.media.upload'), [
            'file' => UploadedFile::fake()->image('screenshot.png'),
        ])->assertUnauthorized();
    }
}
