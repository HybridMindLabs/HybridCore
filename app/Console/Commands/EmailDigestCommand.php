<?php

namespace App\Console\Commands;

use App\Mail\DigestMail;
use App\Models\NewsArticle;
use App\Models\Server;
use App\Models\User;
use App\Services\Extensions\Registries\ScheduledReportRegistry;
use App\Services\Mail\EmailLogService;
use App\Services\Mail\MailConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class EmailDigestCommand extends Command
{
    protected $signature = 'hybridcore:email:digest';

    protected $description = 'Send a weekly digest (unread notifications + community news/servers) to users who have not opted out';

    public function handle(MailConfigService $config, EmailLogService $logs): int
    {
        $config->applyFromDatabase();

        // Community items are the same for everyone — build them once.
        $communityHtml = $this->communityItemsHtml();
        $communityCount = substr_count($communityHtml, '<li>');
        $sent = 0;

        User::query()
            ->whereNull('banned_at')
            ->whereNotNull('email_verified_at')
            ->chunkById(100, function ($users) use ($logs, $communityHtml, $communityCount, &$sent) {
                foreach ($users as $user) {
                    $prefs = $user->notification_preferences ?? [];

                    if (($prefs['email_digest'] ?? true) === false) {
                        continue;
                    }

                    $unread = $user->unreadNotifications()->latest()->limit(10)->get();

                    // Nothing personal AND nothing new in the community → skip.
                    if ($unread->isEmpty() && $communityHtml === '') {
                        continue;
                    }

                    $itemsHtml = $unread->map(function ($n) {
                        $label = match ($n->data['type'] ?? null) {
                            'new_message' => 'New message from '.($n->data['sender_username'] ?? 'someone'),
                            default => $n->data['preview'] ?? 'New notification',
                        };

                        return '<li>'.e($label).'</li>';
                    })->implode('');

                    $itemsHtml .= $communityHtml;

                    try {
                        Mail::to($user->email)->queue(new DigestMail($user, $unread->count() + $communityCount, $itemsHtml));
                        $logs->log($user->email, 'Your digest from '.config('app.name'), 'sent', 'digest');
                        $sent++;
                    } catch (\Throwable $e) {
                        $logs->log($user->email, 'Your digest from '.config('app.name'), 'failed', 'digest', $e->getMessage());
                    }
                }
            });

        $this->info("Digest sent to {$sent} users.");

        return self::SUCCESS;
    }

    /** News + new servers from the last 7 days, as <li> items. */
    private function communityItemsHtml(): string
    {
        $items = [];

        if (Schema::hasTable('news_articles')) {
            $articles = NewsArticle::published()
                ->where('published_at', '>=', now()->subDays(7))
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(['title', 'slug']);

            foreach ($articles as $article) {
                $url = rtrim(config('app.url'), '/').'/news/'.$article->slug;
                $items[] = '<li>News: <a href="'.e($url).'">'.e($article->title).'</a></li>';
            }
        }

        if (Schema::hasTable('servers')) {
            $servers = Server::active()
                ->where('created_at', '>=', now()->subDays(7))
                ->with('game')
                ->limit(5)
                ->get();

            foreach ($servers as $server) {
                $name = $server->name ?? $server->address;
                $items[] = '<li>New server: '.e($name).($server->game ? ' ('.e($server->game->name).')' : '').'</li>';
            }
        }

        // Extension-contributed digest rows (text is escaped; optional link).
        foreach (app(ScheduledReportRegistry::class)->collect() as $row) {
            $text = e($row['text']);
            $items[] = $row['url']
                ? '<li><a href="'.e($row['url']).'">'.$text.'</a></li>'
                : '<li>'.$text.'</li>';
        }

        return implode('', $items);
    }
}
