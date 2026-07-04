<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(self::PER_PAGE);

        return Inertia::render('Account/Notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $items = $request->user()
            ->notifications()
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? 'system',
                'data' => $n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json(['notifications' => $items]);
    }

    public function markRead(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('account.notifications_marked_read'));
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
