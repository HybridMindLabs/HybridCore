<?php

namespace App\Jobs;

use App\Models\NewsComment;
use App\Models\ServerReview;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Support\Filters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateDataExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $user) {}

    public function handle(): void
    {
        $user = $this->user->load(['connectedAccounts', 'roles', 'achievements', 'following', 'followers']);

        $export = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'username' => $user->username,
                'display_name' => $user->getRawOriginal('display_name'),
                'email' => $user->email,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'connected_accounts' => $user->connectedAccounts->map(fn ($a) => [
                'provider' => $a->provider,
                'username' => $a->provider_username,
                'connected_at' => $a->created_at->toIso8601String(),
            ]),
            'roles' => $user->roles->pluck('name'),
            'achievements' => $user->achievements->pluck('slug'),
            'following' => $user->following->pluck('username'),
            'followers' => $user->followers->pluck('username'),
            'reviews' => ServerReview::where('user_id', $user->id)->get()
                ->map(fn ($r) => ['rating' => $r->rating, 'body' => $r->body, 'at' => $r->created_at->toIso8601String()]),
            'comments' => NewsComment::where('user_id', $user->id)->get()
                ->map(fn ($c) => ['body' => $c->body, 'at' => $c->created_at->toIso8601String()]),
        ];

        // Extensions append their own sections (keyed by slug) — stats, etc.
        $export = app(FilterRegistry::class)->apply(Filters::DATA_EXPORT, $export, $user);

        $filename = 'user_'.$user->id.'_'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put('exports/'.$filename, json_encode($export, JSON_PRETTY_PRINT));

        $user->notify(new SystemNotification(
            __('account.export_ready'),
            'success',
            route('account.export.download', $filename),
            __('account.export_download'),
        ));
    }
}
