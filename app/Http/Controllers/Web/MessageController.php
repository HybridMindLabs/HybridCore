<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\NewMessageMail;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\NewMessageNotification;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Services\SettingsService;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AchievementService $achievements,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::with(['participant1', 'participant2', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->where(fn ($q) => $q
                ->where('participant_1_id', $user->id)
                ->orWhere('participant_2_id', $user->id))
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'other' => $this->participantData($c->otherParticipant($user->id)),
                'last_message' => $c->latestMessage ? [
                    'body' => $c->latestMessage->deleted_at
                        ? __('account.message_deleted')
                        : $c->latestMessage->body,
                    'is_mine' => $c->latestMessage->sender_id === $user->id,
                    'at' => $c->latestMessage->created_at->diffForHumans(),
                ] : null,
                'unread' => $c->unread_count,
            ]);

        return Inertia::render('Account/Messages/Index', [
            'conversations' => $conversations,
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $conversations->sum('unread'),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();
        $this->authorizeConversation($conversation, $user->id);

        // Mark all incoming messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with('sender')
            ->latest()
            ->cursorPaginate(40);

        return Inertia::render('Account/Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'other' => $this->participantData($conversation->otherParticipant($user->id)),
            ],
            'messages' => $messages->through(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->deleted_at ? null : $m->body,
                'deleted' => $m->deleted_at !== null,
                'is_mine' => $m->sender_id === $user->id,
                'at' => $m->created_at->toIso8601String(),
                'at_human' => $m->created_at->diffForHumans(),
            ]),
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $user->unreadMessagesCount(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->assertDmsEnabled();

        $data = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
        ]);

        $sender = $request->user();
        $recipient = User::where('username', $data['username'])->firstOrFail();

        if ($recipient->id === $sender->id) {
            return back()->withErrors(['username' => __('account.dm_self')]);
        }

        $this->assertNotBlocked($sender->id, $recipient->id);

        $conversation = Conversation::firstOrCreateBetween($sender->id, $recipient->id);

        return redirect()->route('account.messages.show', $conversation->id);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->assertDmsEnabled();
        $user = $request->user();
        $this->authorizeConversation($conversation, $user->id);

        $maxLen = (int) $this->settings->get('dm_max_length', 1000);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.$maxLen],
        ]);

        $recipient = $conversation->otherParticipant($user->id);
        $this->assertNotBlocked($user->id, $recipient->id);
        $this->assertDailyLimit($user->id);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        app(HookRegistry::class)->fire(Hooks::MESSAGE_SENT, $message);

        // Notify recipient (skip if they're blocked or have opted out)
        $preview = mb_substr($data['body'], 0, 80).(mb_strlen($data['body']) > 80 ? '…' : '');
        $recipient->notify(new NewMessageNotification($user, $conversation->id, $preview));

        // Send email to offline recipient
        try {
            if (! $recipient->last_seen_at || $recipient->last_seen_at->lt(now()->subMinutes(10))) {
                Mail::to($recipient->email)->queue(new NewMessageMail($message, $recipient));
            }
        } catch (\Exception) {
        }

        $this->achievements->check($user);

        return back();
    }

    public function deleteMessage(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeConversation($conversation, $user->id);

        if ($message->sender_id !== $user->id) {
            abort(403);
        }

        $message->delete(); // soft delete

        return back();
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function authorizeConversation(Conversation $conversation, int $userId): void
    {
        if ($conversation->participant_1_id !== $userId && $conversation->participant_2_id !== $userId) {
            abort(403);
        }
    }

    private function assertDmsEnabled(): void
    {
        if (! $this->settings->get('dm_enabled', '1')) {
            throw ValidationException::withMessages(['body' => __('account.dm_disabled')]);
        }
    }

    private function assertNotBlocked(int $senderId, int $recipientId): void
    {
        $blocked = UserBlock::where(function ($q) use ($senderId, $recipientId): void {
            $q->where('blocker_id', $senderId)->where('blocked_id', $recipientId);
        })->orWhere(function ($q) use ($senderId, $recipientId): void {
            $q->where('blocker_id', $recipientId)->where('blocked_id', $senderId);
        })->exists();

        if ($blocked) {
            throw ValidationException::withMessages(['body' => __('account.dm_blocked')]);
        }
    }

    private function assertDailyLimit(int $userId): void
    {
        $limit = (int) $this->settings->get('dm_daily_limit', 50);
        $cacheKey = 'dm_daily_'.$userId.'_'.date('Y-m-d');

        $count = (int) Cache::get($cacheKey, 0);

        if ($count >= $limit) {
            throw ValidationException::withMessages(['body' => __('account.dm_daily_limit_reached')]);
        }

        Cache::put($cacheKey, $count + 1, now()->endOfDay());
    }

    private function participantData(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name ?: $user->username,
            'avatar' => $user->avatar,
        ];
    }
}
