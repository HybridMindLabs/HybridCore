<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AchievementService;
use App\Services\Media\AvatarService;
use App\Services\Media\BannerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private readonly AvatarService $avatars,
        private readonly BannerService $banners,
        private readonly AchievementService $achievements,
    ) {}

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'max:10240']]);

        $user = $request->user();
        $url = $this->avatars->upload($user, $request->file('avatar'));

        $user->update(['avatar' => $url]);
        $this->achievements->check($user);

        return back()->with('success', __('account.avatar_updated'));
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->avatars->delete($user);
        $user->update(['avatar' => null]);

        return back()->with('success', __('account.avatar_removed'));
    }

    public function uploadBanner(Request $request): RedirectResponse
    {
        $request->validate(['banner' => ['required', 'file', 'image', 'max:10240']]);

        $user = $request->user();
        $url = $this->banners->upload($user, $request->file('banner'));

        $user->update(['banner' => $url]);
        $this->achievements->check($user);

        return back()->with('success', __('account.banner_updated'));
    }

    public function deleteBanner(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->banners->delete($user);
        $user->update(['banner' => null]);

        return back()->with('success', __('account.banner_removed'));
    }
}
