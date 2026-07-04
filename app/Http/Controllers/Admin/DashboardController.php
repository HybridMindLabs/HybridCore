<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Message;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Services\Extensions\Registries\WidgetRegistry;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(WidgetRegistry $widgets, SettingsService $settings): Response
    {
        $maintenanceMode = (bool) $settings->get('maintenance_mode', false);
        $serversTotal = null;
        $serversOnline = null;
        $topGames = null;

        if (class_exists(Server::class)) {
            $serversTotal = Server::count();
            $serversOnline = Server::whereHas('latestSnapshot', fn ($q) => $q->where('is_online', true))->count();

            $topGames = Server::with('game')
                ->select('game_id', DB::raw('COUNT(*) as total'))
                ->groupBy('game_id')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->game?->name ?? 'Unknown',
                    'total' => $row->total,
                ]);
        }

        $totalMessages = null;
        if (class_exists(Message::class)) {
            $totalMessages = Message::withTrashed()->count();
        }

        // 30-day registrations per day
        $regLast30 = User::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        // Fill missing days with 0
        $regMap = $regLast30->pluck('count', 'date')->all();
        $regFilled = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $regFilled->push(['date' => $date, 'count' => $regMap[$date] ?? 0]);
        }

        // Logins last 30 days (if login_histories table exists)
        $loginLast30 = collect();
        try {
            $logins = DB::table('login_histories')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $loginLast30->push(['date' => $date, 'count' => (int) ($logins[$date]->count ?? 0)]);
            }
        } catch (\Exception) {
            // login_histories table may not exist in all installs
        }

        // Role distribution
        $roleDistribution = collect();
        try {
            if (class_exists(Role::class)) {
                $roleDistribution = DB::table('user_role')
                    ->join('roles', 'roles.id', '=', 'user_role.role_id')
                    ->where('user_role.is_primary', true)
                    ->select('roles.name', 'roles.color', DB::raw('COUNT(*) as count'))
                    ->groupBy('roles.id', 'roles.name', 'roles.color')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($r) => ['name' => $r->name, 'color' => $r->color, 'count' => (int) $r->count]);
            }
        } catch (\Exception) {
            // user_role table may not exist yet
        }

        // Users without primary role
        $usersNoRole = User::doesntHave('roles')->count();
        if ($usersNoRole > 0) {
            $roleDistribution->push(['name' => 'No role', 'color' => '#3f3f46', 'count' => $usersNoRole]);
        }

        // Most-clicked servers (connect clicks, last 30 days)
        $topClickedServers = collect();
        try {
            $topClickedServers = DB::table('server_connection_clicks')
                ->join('servers', 'servers.id', '=', 'server_connection_clicks.server_id')
                ->where('server_connection_clicks.created_at', '>=', now()->subDays(30))
                ->select('servers.name', 'servers.ip', 'servers.port', DB::raw('COUNT(*) as clicks'))
                ->groupBy('servers.id', 'servers.name', 'servers.ip', 'servers.port')
                ->orderByDesc('clicks')
                ->limit(5)
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->name ?? "{$r->ip}:{$r->port}",
                    'clicks' => (int) $r->clicks,
                ]);
        } catch (\Exception) {
            // table may not exist in all installs
        }

        // Community engagement counters (tables may not exist in all installs)
        $engagement = [];
        foreach ([
            'reviews' => 'server_reviews',
            'comments' => 'news_comments',
            'follows' => 'user_follows',
        ] as $key => $tableName) {
            try {
                $engagement[$key] = DB::table($tableName)->count();
                $engagement[$key.'_week'] = DB::table($tableName)->where('created_at', '>=', now()->subDays(7))->count();
            } catch (\Exception) {
                $engagement[$key] = null;
                $engagement[$key.'_week'] = null;
            }
        }

        return Inertia::render('Admin/Dashboard', [
            'widgets' => $widgets->compose(),
            'maintenance_mode' => $maintenanceMode,
            'stats' => [
                'total_users' => User::count(),
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
                'banned_users' => User::whereNotNull('banned_at')->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'servers_total' => $serversTotal,
                'servers_online' => $serversOnline,
                'total_messages' => $totalMessages,
                'registrations_last_30_days' => $regFilled,
                'logins_last_30_days' => $loginLast30,
                'role_distribution' => $roleDistribution,
                'top_games' => $topGames,
                'contact_total' => ContactMessage::count(),
                'contact_unread' => ContactMessage::whereNull('read_at')->count(),
                'top_clicked_servers' => $topClickedServers,
                'engagement' => $engagement,
            ],
        ]);
    }
}
