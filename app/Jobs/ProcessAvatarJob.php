<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Media\AvatarService;
use App\Services\Media\BannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes an uploaded avatar or banner off the request cycle.
 *
 * Mirrors the synchronous path in MediaController: hand the file to the
 * matching media service and persist the URL it returns on the user.
 */
class ProcessAvatarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $tempPath,
        public readonly string $type, // 'avatar' | 'banner'
    ) {}

    public function handle(AvatarService $avatars, BannerService $banners): void
    {
        $user = User::findOrFail($this->userId);
        $file = new UploadedFile($this->tempPath, basename($this->tempPath), null, null, true);

        if ($this->type === 'banner') {
            $user->update(['banner' => $banners->upload($user, $file)]);
        } else {
            $user->update(['avatar' => $avatars->upload($user, $file)]);
        }

        @unlink($this->tempPath);
    }

    public function failed(\Throwable $e): void
    {
        @unlink($this->tempPath);
    }
}
