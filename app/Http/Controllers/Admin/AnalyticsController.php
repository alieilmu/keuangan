<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly BusinessAnalyticsService $analytics) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Analytics/Index', [
            'summary' => $this->analytics->summary(),
            'growth' => $this->analytics->userGrowth()->values(),
        ]);
    }
}
