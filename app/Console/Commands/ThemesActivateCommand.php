<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Services\Themes\ThemeManager;
use Illuminate\Console\Command;

class ThemesActivateCommand extends Command
{
    protected $signature = 'hybridcore:themes:activate {slug : Theme slug (e.g. Default)}';

    protected $description = 'Activate a theme by slug';

    public function handle(ThemeManager $manager): int
    {
        $slug = $this->argument('slug');

        $theme = Theme::where('slug', $slug)->first();

        if (! $theme) {
            $this->error("Theme '{$slug}' not found. Run hybridcore:themes:sync first.");

            return self::FAILURE;
        }

        if ($theme->active) {
            $this->info("{$theme->name} is already active.");

            return self::SUCCESS;
        }

        $manager->activate($theme);

        $this->info("{$theme->name} is now the active theme.");

        return self::SUCCESS;
    }
}
