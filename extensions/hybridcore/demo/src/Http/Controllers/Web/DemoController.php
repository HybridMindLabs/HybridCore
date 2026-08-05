<?php

namespace Hybridcore\Demo\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Extensions/hybridcore/demo/Web/Index', [
            'message' => trans('demo::messages.welcome'),
        ]);
    }

    /** accountTabs() example — rendered inside AccountPage, matching every other tab in /account. */
    public function account(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Extensions/hybridcore/demo/Account/Index', [
            'message' => trans('demo::messages.welcome'),
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $user->unreadMessagesCount(),
        ]);
    }
}
