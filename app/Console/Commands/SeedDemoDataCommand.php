<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seeds throwaway demo content (users, servers, news) for local development
 * — never intended for production. Existing rows are left untouched; this
 * only adds new ones, so it's safe to run repeatedly.
 */
class SeedDemoDataCommand extends Command
{
    protected $signature = 'hybridcore:demo:seed
        {--users=10 : Number of demo users to create}
        {--servers=8 : Number of demo servers to create}
        {--articles=6 : Number of demo news articles to create}';

    protected $description = 'Seed demo users, servers, and news articles for local testing (not for production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }

        $this->seedUsers((int) $this->option('users'));
        $this->seedServers((int) $this->option('servers'));
        $this->seedNews((int) $this->option('articles'));

        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }

    private function seedUsers(int $count): void
    {
        User::factory()->count($count)->create();
        $this->info("Created {$count} demo users.");
    }

    private function seedServers(int $count): void
    {
        if (Game::count() === 0) {
            $this->call('db:seed', ['--class' => 'GameSeeder']);
        }

        Server::factory()->count($count)->create();
        $this->info("Created {$count} demo servers.");
    }

    private function seedNews(int $count): void
    {
        $category = NewsCategory::firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'General', 'color' => '#3b82f6', 'icon' => 'newspaper'],
        );

        $author = User::first() ?? User::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $title = fake()->sentence(6);

            NewsArticle::create([
                'category_id' => $category->id,
                'author_id' => $author->id,
                'title' => $title,
                'slug' => Str::slug($title).'-'.Str::random(6),
                'excerpt' => fake()->sentence(20),
                'body' => collect(fake()->paragraphs(5))->implode("\n\n"),
                'format' => 'markdown',
                'status' => 'published',
                'published_at' => now()->subDays(random_int(0, 30)),
            ]);
        }

        $this->info("Created {$count} demo news articles.");
    }
}
