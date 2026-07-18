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

        // The category comes straight from the URL, so it has to be checked
        // against the real key list — otherwise any string lands as a new key
        // in the preferences JSON that nothing will ever read.
        $key = 'email_'.$category;
        abort_unless(in_array($key, User::EMAIL_PREFERENCE_KEYS, true), 404);

        $prefs = $user->notification_preferences ?? [];
        $prefs[$key] = false;
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
