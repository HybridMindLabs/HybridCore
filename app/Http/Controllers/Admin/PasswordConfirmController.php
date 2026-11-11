<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireRecentPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PasswordConfirmController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/Auth/ConfirmPassword');
    }

    public function store(Request $request): JsonResponse|RedirectResponse|SymfonyResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $request->session()->put(RequireRecentPassword::SESSION_KEY, time());

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['confirmed' => true]);
        }

        $intended = $request->session()->pull('url.intended', route('admin.dashboard'));

        // The page that asked is usually a file download, which an Inertia
        // XHR redirect can't follow — force a full navigation instead.
        return $request->header('X-Inertia')
            ? Inertia::location($intended)
            : redirect()->to($intended);
    }
}
