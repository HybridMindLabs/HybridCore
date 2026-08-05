<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Break-glass recovery: if every admin gets locked out of the panel by the
 * mandatory-2FA policy (e.g. nobody set 2FA up before their grace period
 * elapsed), nobody can reach Admin > Settings to turn the policy back off
 * either — by design, that's what "enforced" means. This command flips the
 * setting from the shell instead, where DB/file access is still available.
 */
class SecurityDisableTwoFactorEnforcementCommand extends Command
{
    protected $signature = 'hybridcore:security:disable-2fa-enforcement {--force : Confirm disabling the policy}';

    protected $description = 'Turn off the mandatory-2FA-for-admins policy (DANGEROUS — recovery use only)';

    public function handle(SettingsService $settings): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to disable without --force.');
            $this->line('This turns off Admin > Settings > Security > "Require 2FA for admin access" for everyone.');
            $this->line('Use it only to recover from a full admin lockout, then re-enable it once someone is back in.');
            $this->line('If you really mean it: php artisan hybridcore:security:disable-2fa-enforcement --force');

            return self::FAILURE;
        }

        $settings->set('security.require_2fa_for_admins', '0');

        $this->warn('Mandatory 2FA for admins is now OFF. Re-enable it from Admin > Settings > Security once you are back in.');

        return self::SUCCESS;
    }
}
