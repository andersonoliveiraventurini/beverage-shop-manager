<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentMethodBreakdown extends StatsOverviewWidget
{
    protected ?string $heading = 'Por forma de pagamento (mês atual)';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    protected function getStats(): array
    {
        $rows = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('payment_method, COUNT(*) AS qty, SUM(total) AS revenue')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $row = fn (string $key) => $rows->get($key) ?? (object) ['qty' => 0, 'revenue' => 0];

        $format = fn (string $label, string $key, string $color) => Stat::make(
            $label,
            'R$ ' . number_format((float) $row($key)->revenue, 2, ',', '.'),
        )
            ->description((int) $row($key)->qty . ' vendas')
            ->color($color);

        return [
            $format('Dinheiro', Sale::PAYMENT_CASH, 'success'),
            $format('PIX', Sale::PAYMENT_PIX, 'info'),
            $format('Débito', Sale::PAYMENT_DEBIT, 'warning'),
            $format('Crédito', Sale::PAYMENT_CREDIT, 'primary'),
        ];
    }
}
