<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsComment;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\NewCommentNotification;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsCommentController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function store(Request $request, NewsArticle $article): RedirectResponse
    {
        abort_unless($article->isPublished(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $commenter = $request->user();

        $comment = NewsComment::create([
            'article_id' => $article->id,
            'user_id' => $commenter->id,
            'body' => $data['body'],
        ]);

        app(HookRegistry::class)->fire(Hooks::COMMENT_CREATED, $comment);

        // Notify the article author (unless they commented themselves).
        $article->loadMissing('author');
        if ($article->author && $article->author->id !== $commenter->id) {
            $article->author->notify(new NewCommentNotification($commenter, $article, $data['body']));
        }

        $this->notifyMentions($commenter, $article, $data['body']);

        $this->achievements->check($commenter);

        return back()->with('success', 'Comment posted.');
    }

    /** Notify every @username mentioned in the comment (except self/author-dup). */
    private function notifyMentions(User $commenter, NewsArticle $article, string $body): void
    {
        preg_match_all('/@([a-zA-Z0-9_\-\.]{2,30})/', $body, $matches);

        $usernames = collect($matches[1] ?? [])->unique()->take(5);

        if ($usernames->isEmpty()) {
            return;
        }

        User::whereIn('username', $usernames)
            ->whereNull('banned_at')
            ->where('id', '!=', $commenter->id)
            ->get()
            ->each(fn (User $user) => $user->notify(new MentionNotification(
                $commenter,
                'a comment on "'.Str::limit($article->title, 50).'"',
                $body,
                route('news.show', $article->slug),
            )));
    }

    public function destroy(Request $request, NewsArticle $article, NewsComment $comment): RedirectResponse
    {
        abort_unless($comment->article_id === $article->id, 404);
        abort_unless($request->user()->id === $comment->user_id || $request->user()->is_admin, 403);

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
