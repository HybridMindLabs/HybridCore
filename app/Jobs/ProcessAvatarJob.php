<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Media\AvatarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAvatarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $tempPath,
        public readonly string $type, // 'avatar' | 'banner'
    ) {}

    public function handle(AvatarService $service): void
    {
        $user = User::findOrFail($this->userId);
        $file = new UploadedFile($this->tempPath, basename($this->tempPath), null, null, true);

        if ($this->type === 'banner') {
            $service->updateBanner($user, $file);
        } else {
            $service->updateAvatar($user, $file);
        }
    }

    public function failed(\Throwable $e): void
    {
        @unlink($this->tempPath);
    }
}
