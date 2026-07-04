<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerReview;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerReviewController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function store(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = ServerReview::updateOrCreate(
            ['user_id' => auth()->id(), 'server_id' => $server->id],
            $data,
        );

        app(HookRegistry::class)->fire(Hooks::REVIEW_CREATED, $review);

        $this->achievements->check($request->user());

        return back()->with('success', 'Review saved.');
    }

    public function destroy(Request $request, Server $server, ServerReview $review): RedirectResponse
    {
        abort_unless(auth()->id() === $review->user_id || auth()->user()?->is_admin, 403);
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
