<?php

namespace Tests\Feature;

use App\Mail\DigestMail;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDigestTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(): NewsArticle
    {
        return NewsArticle::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'Fresh news',
            'slug' => 'fresh-news',
            'body' => 'Body',
            'format' => 'markdown',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_digest_sent_to_verified_users_when_community_news_exists(): void
    {
        Mail::fake();

        $this->publishedArticle();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->artisan('hybridcore:email:digest')->assertSuccessful();

        Mail::assertQueued(DigestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_digest_skips_unverified_users(): void
    {
        Mail::fake();

        $this->publishedArticle();
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->artisan('hybridcore:email:digest')->assertSuccessful();

        Mail::assertNotQueued(DigestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_digest_respects_opt_out_preference(): void
    {
        Mail::fake();

        $this->publishedArticle();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'notification_preferences' => ['email_digest' => false],
        ]);

        $this->artisan('hybridcore:email:digest')->assertSuccessful();

        Mail::assertNotQueued(DigestMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_digest_skipped_when_nothing_to_report(): void
    {
        Mail::fake();

        // No news, no servers, no unread notifications.
        User::factory()->create(['email_verified_at' => now()]);

        $this->artisan('hybridcore:email:digest')->assertSuccessful();

        Mail::assertNotQueued(DigestMail::class);
    }
}
