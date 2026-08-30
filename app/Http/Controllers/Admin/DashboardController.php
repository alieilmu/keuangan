<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\BusinessAnalyticsService;
use App\Services\SystemHealthService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BusinessAnalyticsService $analytics,
        private readonly SystemHealthService $health,
    ) {}

    public function __invoke(): Response
    {
        $health = $this->health->snapshot();

        return Inertia::render('Admin/Dashboard', [
            'summary' => $this->analytics->summary(),
            'total_admins' => User::query()->where('is_admin', true)->count(),
            'blocked_users' => User::query()->where('is_blocked', true)->count(),
            'total_groups' => Group::query()->count(),
            'health_ok' => ($health['database']['ok'] ?? false) && ($health['redis']['ok'] ?? false),
        ]);
    }
}
