<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /** Category key => event prefixes it covers. Keep in sync with every activity()->log() call site. */
    private const CATEGORIES = [
        'users' => ['user.'],
        'roles' => ['roles.'],
        'rules' => ['rules.'],
        'pages' => ['page.'],
        'legal' => ['legal.'],
        'news' => ['news.'],
        'servers' => ['server.', 'servers.'],
        'webhooks' => ['webhook.'],
        'tokens' => ['service_account.'],
        'extensions' => ['extension.', 'extensions.'],
        'themes' => ['theme.', 'themes.'],
        'reports' => ['report.'],
        'backups' => ['backup.'],
        'trash' => ['trash.'],
        'system' => ['system.', 'settings.'],
    ];

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->toString();

        $logs = ActivityLog::with('causer')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhereHas('causer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when($category && isset(self::CATEGORIES[$category]), function ($q) use ($category) {
                $q->where(function ($q) use ($category) {
                    foreach (self::CATEGORIES[$category] as $prefix) {
                        $q->orWhere('event', 'like', "{$prefix}%");
                    }
                });
            })
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'causer' => $log->causer instanceof User
                    ? ['name' => $log->causer->name, 'email' => $log->causer->email]
                    : null,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/ActivityLog/Index', [
            'logs' => $logs,
            'filters' => ['search' => $search, 'category' => $category],
        ]);
    }
}
