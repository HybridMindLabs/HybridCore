<?php

namespace App\Services\Media;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AvatarService
{
    private const RATE_KEY = 'avatar_change_%d';

    private const RATE_LIMIT = 5;

    private const RATE_WINDOW = 86400; // 24h

    private const OUTPUT_SIZE = 256;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private readonly SettingsService $settings) {}

    public function upload(User $user, UploadedFile $file): string
    {
        $this->assertEnabled();
        $this->assertRateLimit($user);
        $this->assertFileValid($file);

        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());

        // Strip EXIF and resize/crop to square
        $image->cover(self::OUTPUT_SIZE, self::OUTPUT_SIZE);

        $filename = 'avatars/'.$user->id.'.webp';
        Storage::disk('public')->put($filename, $image->toWebp(85));

        $this->incrementRateLimit($user);

        return Storage::disk('public')->url($filename).'?v='.time();
    }

    public function delete(User $user): void
    {
        Storage::disk('public')->delete('avatars/'.$user->id.'.webp');
    }

    private function assertEnabled(): void
    {
        if (! $this->settings->get('avatar_enabled', '1')) {
            throw ValidationException::withMessages(['avatar' => __('account.avatar_disabled')]);
        }
    }

    private function assertRateLimit(User $user): void
    {
        $key = sprintf(self::RATE_KEY, $user->id);
        $count = (int) Cache::get($key, 0);

        if ($count >= self::RATE_LIMIT) {
            throw ValidationException::withMessages(['avatar' => __('account.avatar_rate_limit')]);
        }
    }

    private function assertFileValid(UploadedFile $file): void
    {
        $maxKb = (int) $this->settings->get('avatar_max_kb', 2048);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'avatar' => __('account.avatar_too_large', ['max' => $maxKb]),
            ]);
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['avatar' => __('account.avatar_invalid_type')]);
        }
    }

    private function incrementRateLimit(User $user): void
    {
        $key = sprintf(self::RATE_KEY, $user->id);
        $ttl = self::RATE_WINDOW - (time() % self::RATE_WINDOW);

        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, $ttl);
        }
    }
}
