<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Analytics/Index', [
            'today' => $this->analytics->daySummary(),
            'chart' => $this->analytics->dailyChart(30),
            'pages' => $this->analytics->topPages(10),
            'devices' => $this->analytics->deviceBreakdown(),
            'online' => $this->analytics->onlineNow(),
        ]);
    }
}
