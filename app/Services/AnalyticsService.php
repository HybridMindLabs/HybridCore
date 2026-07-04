<?php

namespace App\Services;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** Detailed "who is online" — last 5 minutes. */
    public function whoIsOnline(): array
    {
        return Cache::remember('analytics.who_is_online', 30, function () {
            $since = now()->subMinutes(5);

            $rows = PageView::where('created_at', '>=', $since)
                ->select('session_id', 'user_id', 'is_bot')
                ->get();

            $userIds = $rows->whereNotNull('user_id')->where('is_bot', false)->pluck('user_id')->unique();

            $users = User::whereIn('id', $userIds)
                ->get(['id', 'name', 'username', 'avatar'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'avatar' => $u->avatar,
                ]);

            $humanRows = $rows->where('is_bot', false);
            $guests = $humanRows->whereNull('user_id')->pluck('session_id')->unique()->count();
            $bots = $rows->where('is_bot', true)->pluck('session_id')->unique()->count();

            return [
                'users' => $users->values()->all(),
                'guests' => $guests,
                'bots' => $bots,
            ];
        });
    }

    /** Detailed "active last 24h". */
    public function activeToday(): array
    {
        return Cache::remember('analytics.active_today', 120, function () {
            $since = now()->subHours(24);

            $rows = PageView::where('created_at', '>=', $since)
                ->select('session_id', 'user_id', 'is_bot')
                ->get();

            $userIds = $rows->whereNotNull('user_id')->where('is_bot', false)->pluck('user_id')->unique();

            $users = User::whereIn('id', $userIds)
                ->get(['id', 'name', 'username', 'avatar'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'avatar' => $u->avatar,
                ]);

            $humanRows = $rows->where('is_bot', false);
            $guests = $humanRows->whereNull('user_id')->pluck('session_id')->unique()->count();
            $bots = $rows->where('is_bot', true)->pluck('session_id')->unique()->count();

            return [
                'users' => $users->values()->all(),
                'guests' => $guests,
                'bots' => $bots,
            ];
        });
    }

    /** Visitors online in the last 5 minutes (non-bot unique sessions). */
    public function onlineNow(): int
    {
        return Cache::remember('analytics.online_now', 60, fn () => PageView::where('is_bot', false)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->distinct('session_id')
            ->count('session_id'));
    }

    /** Summary stats for a given date (defaults to today). */
    public function daySummary(?Carbon $date = null): array
    {
        $date ??= today();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return Cache::remember("analytics.day.{$date->toDateString()}", 120, function () use ($start, $end) {
            $rows = PageView::whereBetween('created_at', [$start, $end]);

            $total = (clone $rows)->count();
            $bots = (clone $rows)->where('is_bot', true)->count();
            $humans = $total - $bots;
            $unique = (clone $rows)->where('is_bot', false)->distinct('session_id')->count('session_id');
            $registered = (clone $rows)->where('is_bot', false)->whereNotNull('user_id')->distinct('user_id')->count('user_id');

            return compact('total', 'bots', 'humans', 'unique', 'registered');
        });
    }

    /** Daily visit totals for the last N days (humans only). */
    public function dailyChart(int $days = 30): array
    {
        return Cache::remember("analytics.chart.{$days}", 300, function () use ($days) {
            $rows = PageView::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT session_id) as unique_sessions'),
                DB::raw('SUM(is_bot) as bots')
            )
                ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $result = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = today()->subDays($i)->toDateString();
                $row = $rows[$d] ?? null;
                $result[] = [
                    'date' => $d,
                    'total' => (int) ($row?->total ?? 0),
                    'unique' => (int) ($row?->unique_sessions ?? 0),
                    'bots' => (int) ($row?->bots ?? 0),
                ];
            }

            return $result;
        });
    }

    /** Top N pages by human views today. */
    public function topPages(int $limit = 10, ?Carbon $date = null): array
    {
        $date ??= today();

        return Cache::remember("analytics.top_pages.{$date->toDateString()}", 120, function () use ($limit, $date) {
            return PageView::select('path', DB::raw('COUNT(*) as views'))
                ->where('is_bot', false)
                ->whereDate('created_at', $date)
                ->groupBy('path')
                ->orderByDesc('views')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /** Device breakdown for today (humans only). */
    public function deviceBreakdown(?Carbon $date = null): array
    {
        $date ??= today();

        return Cache::remember("analytics.devices.{$date->toDateString()}", 120, function () use ($date) {
            return PageView::select('device_type', DB::raw('COUNT(*) as count'))
                ->where('is_bot', false)
                ->whereDate('created_at', $date)
                ->groupBy('device_type')
                ->pluck('count', 'device_type')
                ->toArray();
        });
    }

    /** Delete rows older than $days days. Called by scheduler. */
    public function prune(int $days = 90): int
    {
        return PageView::where('created_at', '<', now()->subDays($days))->delete();
    }
}
