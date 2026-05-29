<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates a printable PDF summary of the dashboard headline KPIs
 * for a given date range.  Only accessible to authenticated managers.
 *
 * Query params:
 *   startDate  (Y-m-d)  — defaults to first day of current month
 *   endDate    (Y-m-d)  — defaults to last day of current month
 */
class DashboardReportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse|Response
    {
        abort_unless(auth()->user()?->isManager(), 403);

        [$start, $end] = $this->resolvePeriod($request);

        $confirmed = fn (): Builder => Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end);

        $revenue = (float) $confirmed()->sum('total');
        $count = (int) $confirmed()->count();
        $avgTicket = $count > 0 ? $revenue / $count : 0.0;
        $waterRevenue = (float) $confirmed()->where('contains_water', true)->sum('total');
        $waterShare = $revenue > 0 ? round(($waterRevenue / $revenue) * 100, 1) : 0.0;

        $pdf = Pdf::loadView('reports.dashboard', [
            'start' => $start,
            'end' => $end,
            'revenue' => $revenue,
            'count' => $count,
            'avgTicket' => $avgTicket,
            'waterShare' => $waterShare,
        ]);

        $filename = 'dashboard-'.$start->toDateString().'-'.$end->toDateString().'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(Request $request): array
    {
        $startRaw = $request->query('startDate');
        $endRaw = $request->query('endDate');

        $start = ($startRaw !== null && $startRaw !== '')
            ? CarbonImmutable::parse((string) $startRaw)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $end = ($endRaw !== null && $endRaw !== '')
            ? CarbonImmutable::parse((string) $endRaw)->endOfDay()
            : CarbonImmutable::now()->endOfMonth();

        return [$start, $end];
    }
}
