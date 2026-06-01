<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardPeriod;
use App\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCustomersTable extends TableWidget
{
    use InteractsWithPageFilters;
    use ResolvesDashboardPeriod;

    protected static ?string $heading = 'Top 10 clientes do período';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildQuery())
            ->paginated(false)
            ->emptyStateHeading('Nenhuma venda no período')
            ->emptyStateDescription('Ajuste o filtro de datas acima para ver os clientes que mais compraram.')
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->weight('semibold'),
                TextColumn::make('sales_count')
                    ->label('Vendas')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_spent')
                    ->label('Total gasto')
                    ->money('BRL')
                    ->weight('bold')
                    ->alignEnd(),
            ]);
    }

    /** @internal exposed for tests via ReflectionMethod */
    public function buildQuery(): Builder
    {
        [$start, $end] = $this->resolvePeriod();

        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        return Customer::query()
            ->select([
                'customers.id',
                'customers.name as customer_name',
            ])
            ->selectSub(function ($q) use ($startStr, $endStr) {
                $q->from('sales')
                    ->whereColumn('sales.customer_id', 'customers.id')
                    ->where('sales.status', 'confirmed')
                    ->whereDate('sales.created_at', '>=', $startStr)
                    ->whereDate('sales.created_at', '<=', $endStr)
                    ->selectRaw('COALESCE(COUNT(*), 0)');
            }, 'sales_count')
            ->selectSub(function ($q) use ($startStr, $endStr) {
                $q->from('sales')
                    ->whereColumn('sales.customer_id', 'customers.id')
                    ->where('sales.status', 'confirmed')
                    ->whereDate('sales.created_at', '>=', $startStr)
                    ->whereDate('sales.created_at', '<=', $endStr)
                    ->selectRaw('COALESCE(SUM(sales.total), 0)');
            }, 'total_spent')
            ->whereHas('sales', function (Builder $query) use ($startStr, $endStr) {
                $query->where('status', 'confirmed')
                    ->whereDate('created_at', '>=', $startStr)
                    ->whereDate('created_at', '<=', $endStr);
            })
            ->orderByDesc('total_spent')
            ->limit(10);
    }
}
