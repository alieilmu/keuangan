<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function index(): Response
    {
        return Inertia::render('Admin/SystemHealth/Index', [
            'health' => $this->health->snapshot(),
            'checked_at' => now()->translatedFormat('d M Y H:i:s'),
        ]);
    }
}
