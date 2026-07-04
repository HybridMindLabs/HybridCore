<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class UnsubscribeController extends Controller
{
    public function show(Request $request, string $category): Response|RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired unsubscribe link.');
        }

        $user = User::findOrFail($request->query('user'));

        return Inertia::render('Unsubscribe', [
            'category' => $category,
            'username' => $user->username ?? $user->name,
        ]);
    }

    public function destroy(Request $request, string $category): RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired unsubscribe link.');
        }

        $user = User::findOrFail($request->query('user'));

        $prefs = $user->notification_preferences ?? [];
        $prefs['email_'.$category] = false;
        $user->update(['notification_preferences' => $prefs]);

        return redirect()->back()->with('success', 'You have been unsubscribed from '.ucfirst($category).' emails.');
    }

    public static function signedUrl(User $user, string $category): string
    {
        return URL::temporarySignedRoute(
            'unsubscribe.show',
            now()->addDays(7),
            ['category' => $category, 'user' => $user->id],
        );
    }
}
