<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extensions\Registries\MediaUsageRegistry;
use App\Services\Media\MediaLibrary;
use App\Services\Media\NewsImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryExtrasTest extends TestCase
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

    private function media(string $name = 'img'): Media
    {
        return app(MediaLibrary::class)->upload(UploadedFile::fake()->image("{$name}.png", 64, 64), null, $this->admin);
    }

    /** A real two-frame GIF; Imagick builds it because GD cannot write animations. */
    private function animatedGif(): UploadedFile
    {
        $gif = new \Imagick;
        foreach (['red', 'blue'] as $colour) {
            $frame = new \Imagick;
            $frame->newImage(40, 40, new \ImagickPixel($colour));
            $frame->setImageFormat('gif');
            $frame->setImageDelay(20);
            $gif->addImage($frame);
        }
        $path = tempnam(sys_get_temp_dir(), 'gif').'.gif';
        $gif->writeImages($path, true);

        return new UploadedFile($path, 'spin.gif', 'image/gif', null, true);
    }

    public function test_bulk_delete_keeps_images_in_use_until_forced(): void
    {
        $free = $this->media('free');
        $used = $this->media('used');
        Rule::create(['slug' => 'r', 'title' => 'Server rules', 'content' => "![x]({$used->url})"]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.media.bulk-destroy'), ['ids' => [$free->id, $used->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('in_use.0.id', $used->id);

        $this->assertModelMissing($free);
        $this->assertModelExists($used);

        $this->actingAs($this->admin)
            ->postJson(route('admin.media.bulk-destroy'), ['ids' => [$used->id], 'force' => true])
            ->assertJsonPath('deleted', 1);
        $this->assertModelMissing($used);
    }

    public function test_used_on_covers_rules_settings_and_extension_columns(): void
    {
        $media = $this->media();
        Rule::create(['slug' => 'r', 'title' => 'Server rules', 'content' => "![x]({$media->url})"]);
        Setting::create(['key' => 'site_logo', 'value' => $media->url, 'group' => 'general']);
        Setting::create(['key' => 'other', 'value' => 'media/2026/01/'.basename($media->path).'.old', 'group' => 'general']);

        // An extension declaring its own column — here reusing the users table.
        app(MediaUsageRegistry::class)->register('Member', User::class, ['avatar'], fn (User $u) => $u->username);
        $member = User::factory()->create(['avatar' => $media->path]);

        $usages = collect(app(MediaLibrary::class)->usages($media));

        $this->assertEqualsCanonicalizing(['Rule', 'Setting', 'Member'], $usages->pluck('type')->all());
        $this->assertContains($member->username, $usages->pluck('title'));
        $this->assertNull($usages->firstWhere('type', 'Member')['url']);
    }

    public function test_a_source_whose_table_is_missing_is_skipped(): void
    {
        $media = $this->media();
        app(MediaUsageRegistry::class)->register('Gone', NoTableModel::class, ['image'], fn () => 'x');

        $this->assertSame([], app(MediaLibrary::class)->usages($media));
    }

    public function test_animated_gifs_stay_animated(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is not installed.');
        }

        $media = app(MediaLibrary::class)->upload($this->animatedGif(), null, $this->admin);

        $this->assertStringEndsWith('.webp', $media->path);
        $stored = new \Imagick(Storage::disk('public')->path($media->path));
        $this->assertSame(2, $stored->getNumberImages());
    }

    public function test_without_imagick_an_animated_gif_is_kept_as_it_is(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is needed to build the test GIF.');
        }

        $this->app->bind(NewsImageService::class, fn () => new class extends NewsImageService
        {
            protected function canAnimate(): bool
            {
                return false;
            }
        });

        $upload = $this->animatedGif();
        $media = app(MediaLibrary::class)->upload($upload, null, $this->admin);

        $this->assertStringEndsWith('.gif', $media->path);
        $this->assertSame(file_get_contents($upload->getRealPath()), Storage::disk('public')->get($media->path));
    }

    public function test_still_images_are_not_mistaken_for_animations(): void
    {
        $service = app(NewsImageService::class);

        $this->assertFalse($service->isAnimated(UploadedFile::fake()->image('still.gif', 10, 10)->getRealPath()));
        $this->assertFalse($service->isAnimated(UploadedFile::fake()->image('still.webp', 10, 10)->getRealPath()));
    }
}

class NoTableModel extends Model
{
    protected $table = 'table_that_does_not_exist';
}
