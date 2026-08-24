<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $period = self::resolvePeriod($request->query('period'));

        $data = $this->dashboard->forUser($user, $period);

        return Inertia::render('Dashboard', array_merge($data, [
            'greeting' => self::greeting(),
            // Dipakai modal "Catat Transaksi" langsung dari dashboard.
            'accounts' => Account::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance', 'color']),
            'categories' => Category::query()
                ->where('user_id', $user->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'color']),
            'transaction_types' => collect(TransactionType::cases())
                ->map(fn (TransactionType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
        ]));
    }

    /**
     * Terima "YYYY-MM" dari query string, jatuh ke bulan berjalan bila tidak valid.
     */
    public static function resolvePeriod(?string $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
            } catch (\Throwable) {
                // abaikan, pakai bulan berjalan
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    private static function greeting(): string
    {
        $hour = (int) CarbonImmutable::now()->format('H');

        return match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}
