<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Profile/Index');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return redirect()->route('admin.profile.edit')
            ->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->password = $data['password'];
        $user->save();

        return redirect()->route('admin.profile.edit')
            ->with('success', 'Password updated.');
    }
}
