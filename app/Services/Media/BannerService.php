<?php

namespace App\Services\Media;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class BannerService
{
    private const OUTPUT_W = 1200;

    private const OUTPUT_H = 300;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly SettingsService $settings) {}

    public function upload(User $user, UploadedFile $file): string
    {
        $this->assertEnabled();
        $this->assertFileValid($file);

        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());
        $image->cover(self::OUTPUT_W, self::OUTPUT_H);

        $filename = 'banners/'.$user->id.'.webp';
        Storage::disk('public')->put($filename, $image->toWebp(85));

        return Storage::disk('public')->url($filename).'?v='.time();
    }

    public function delete(User $user): void
    {
        Storage::disk('public')->delete('banners/'.$user->id.'.webp');
    }

    private function assertEnabled(): void
    {
        if (! $this->settings->get('banner_enabled', '1')) {
            throw ValidationException::withMessages(['banner' => __('account.banner_disabled')]);
        }
    }

    private function assertFileValid(UploadedFile $file): void
    {
        $maxKb = (int) $this->settings->get('banner_max_kb', 4096);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'banner' => __('account.banner_too_large', ['max' => $maxKb]),
            ]);
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['banner' => __('account.banner_invalid_type')]);
        }
    }
}
