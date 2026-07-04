<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Console\Command;

/**
 * Safety net for time-based achievements (e.g. "veteran") that no single
 * request triggers — most achievements are checked immediately at the
 * relevant action (email verified, 2FA enabled, OAuth connected).
 */
class CheckAchievementsCommand extends Command
{
    protected $signature = 'hybridcore:achievements:check';

    protected $description = 'Re-check achievement eligibility for every user';

    public function handle(AchievementService $achievements): int
    {
        $count = 0;

        User::query()->chunkById(200, function ($users) use ($achievements, &$count) {
            foreach ($users as $user) {
                $achievements->check($user);
                $count++;
            }
        });

        $this->info("Checked achievements for {$count} user(s).");

        return self::SUCCESS;
    }
}
