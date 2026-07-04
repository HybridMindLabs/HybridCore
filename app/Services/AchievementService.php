<?php

namespace App\Services;

use App\Models\Message;
use App\Models\ServerReview;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AchievementService
{
    /** One of the first N accounts ever registered qualifies for "early_adopter". */
    private const EARLY_ADOPTER_USER_LIMIT = 50;

    private const COLLECTOR_SERVER_COUNT = 5;

    private const SOCIALITE_MESSAGE_COUNT = 50;

    private const REVIEWER_PRO_COUNT = 10;

    private const REGULAR_LOGIN_COUNT = 50;

    private const EXPLORER_GAME_COUNT = 3;

    public const COMMENTATOR_COMMENT_COUNT = 10;

    public const POPULAR_FOLLOWER_COUNT = 10;

    /**
     * All achievement definitions.
     * Each entry: slug => [label, description, icon]. "icon" is a Lucide
     * icon component name — keep in sync with the frontend icon maps in
     * resources/js/pages/Web/Profile.vue and Members.vue.
     */
    public static array $definitions = [
        'early_adopter' => ['label' => 'Early Adopter', 'description' => 'One of the first 50 members to join.', 'icon' => 'Sprout'],
        'veteran' => ['label' => 'Veteran',       'description' => 'Member for over 1 year.',       'icon' => 'Medal'],
        'verified' => ['label' => 'Verified',      'description' => 'Email verified.',                'icon' => 'CircleCheck'],
        'steam_linked' => ['label' => 'Steam Linked',  'description' => 'Connected Steam account.',       'icon' => 'Gamepad2'],
        'discord_linked' => ['label' => 'Discord Linked', 'description' => 'Connected Discord account.',     'icon' => 'MessageSquare'],
        'secure' => ['label' => 'Secured',       'description' => 'Enabled two-factor auth.',       'icon' => 'Lock'],
        'collector' => ['label' => 'Collector',     'description' => 'Favourited '.self::COLLECTOR_SERVER_COUNT.' or more servers.', 'icon' => 'Star'],
        'critic' => ['label' => 'Critic',        'description' => 'Left a server review.',          'icon' => 'FileText'],
        'socialite' => ['label' => 'Socialite',     'description' => 'Sent '.self::SOCIALITE_MESSAGE_COUNT.' or more messages.', 'icon' => 'Mail'],
        'complete_profile' => ['label' => 'All Set Up',  'description' => 'Completed avatar, banner, and bio.', 'icon' => 'Puzzle'],
        'reviewer_pro' => ['label' => 'Top Reviewer',  'description' => 'Reviewed '.self::REVIEWER_PRO_COUNT.' or more servers.', 'icon' => 'PenLine'],
        'regular' => ['label' => 'Regular',       'description' => 'Logged in '.self::REGULAR_LOGIN_COUNT.' or more times.', 'icon' => 'Flame'],
        'explorer' => ['label' => 'Explorer',      'description' => 'Favourited servers from '.self::EXPLORER_GAME_COUNT.' different games.', 'icon' => 'Compass'],
        'commentator' => ['label' => 'Commentator',   'description' => 'Posted '.self::COMMENTATOR_COMMENT_COUNT.' or more news comments.', 'icon' => 'MessagesSquare'],
        'popular' => ['label' => 'Popular',       'description' => 'Followed by '.self::POPULAR_FOLLOWER_COUNT.' or more members.', 'icon' => 'Heart'],
    ];

    public function award(User $user, string $slug): bool
    {
        if (! isset(self::$definitions[$slug])) {
            return false;
        }

        return UserAchievement::firstOrCreate([
            'user_id' => $user->id,
            'slug' => $slug,
        ])->wasRecentlyCreated;
    }

    public function revoke(User $user, string $slug): void
    {
        UserAchievement::where('user_id', $user->id)->where('slug', $slug)->delete();
    }

    /** Check and award all automatic achievements for the user. */
    public function check(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            $this->award($user, 'verified');
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->award($user, 'secure');
        }

        if ($user->created_at->diffInDays(now()) >= 365) {
            $this->award($user, 'veteran');
        }

        $user->loadMissing('connectedAccounts');

        if ($user->connectedAccounts->contains('provider', 'steam')) {
            $this->award($user, 'steam_linked');
        }

        if ($user->connectedAccounts->contains('provider', 'discord')) {
            $this->award($user, 'discord_linked');
        }

        if (User::where('id', '<', $user->id)->count() < self::EARLY_ADOPTER_USER_LIMIT) {
            $this->award($user, 'early_adopter');
        }

        if ($user->favouriteServers()->count() >= self::COLLECTOR_SERVER_COUNT) {
            $this->award($user, 'collector');
        }

        if (ServerReview::where('user_id', $user->id)->exists()) {
            $this->award($user, 'critic');
        }

        if (Message::where('sender_id', $user->id)->count() >= self::SOCIALITE_MESSAGE_COUNT) {
            $this->award($user, 'socialite');
        }

        if ($user->avatar && $user->banner && filled($user->bio)) {
            $this->award($user, 'complete_profile');
        }

        if (ServerReview::where('user_id', $user->id)->count() >= self::REVIEWER_PRO_COUNT) {
            $this->award($user, 'reviewer_pro');
        }

        if ($user->loginHistories()->count() >= self::REGULAR_LOGIN_COUNT) {
            $this->award($user, 'regular');
        }

        $favouriteGameCount = $user->favouriteServers()
            ->distinct('game_id')
            ->count('game_id');

        if ($favouriteGameCount >= self::EXPLORER_GAME_COUNT) {
            $this->award($user, 'explorer');
        }

        // Guarded — these tables land with the comments / follow features.
        if (Schema::hasTable('news_comments')
            && DB::table('news_comments')->where('user_id', $user->id)->count() >= self::COMMENTATOR_COMMENT_COUNT) {
            $this->award($user, 'commentator');
        }

        if (Schema::hasTable('user_follows')
            && DB::table('user_follows')->where('followed_id', $user->id)->count() >= self::POPULAR_FOLLOWER_COUNT) {
            $this->award($user, 'popular');
        }
    }

    public function forUser(User $user): array
    {
        return $user->achievements
            ->map(fn ($a) => array_merge(
                self::$definitions[$a->slug] ?? ['label' => $a->slug, 'description' => '', 'icon' => '🏅'],
                ['slug' => $a->slug, 'awarded_at' => $a->awarded_at->toFormattedDateString()]
            ))
            ->toArray();
    }
}
