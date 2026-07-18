<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every session except the one making the request.
 *
 * Shared by the Sessions panel and by a password change, which are the two
 * moments a user expects "sign me out everywhere else" to actually hold.
 */
class SessionSecurityService
{
    public function signOutOtherDevices(Request $request, User $user): void
    {
        // Deleting session rows alone leaves any "remember me" cookie working,
        // and it would log the holder straight back in. There is one remember
        // token per user, so cycling it is the only way to invalidate those —
        // including this device's, which is re-issued below.
        $hadRecaller = $request->cookies->has(Auth::guard()->getRecallerName());

        $user->setRememberToken(Str::random(60));
        $user->save();

        if ($hadRecaller) {
            // Re-issues the recaller against the new token. This rotates the
            // session id too, so the id to keep is read back afterwards.
            Auth::login($user, true);
        }

        // Only the database driver keeps sessions in a table to prune.
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
