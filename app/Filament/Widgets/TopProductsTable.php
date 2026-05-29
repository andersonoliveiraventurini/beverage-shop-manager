<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardPeriod;
use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsTable extends TableWidget
{
    use InteractsWithPageFilters;
    use ResolvesDashboardPeriod;

    protected static ?string $heading = 'Top 10 produtos do período';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('sku'),
                TextColumn::make('product_name')
                    ->label('Produto'),
                TextColumn::make('size')
                    ->label('Variação'),
                TextColumn::make('units_sold')
                    ->label('Unid.')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('revenue')
                    ->label('Receita')
                    ->money('BRL')
                    ->alignEnd(),
            ]);
    }

    private function buildQuery(): Builder
    {
        [$start, $end] = $this->resolvePeriod();

        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        return ProductVariant::query()
            ->select([
                'product_variants.id',
                'product_variants.sku',
                'product_variants.size',
                'products.name as product_name',
            ])
            ->selectSub(function ($q) use ($startStr, $endStr) {
                $q->from('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->whereColumn('sale_items.variant_id', 'product_variants.id')
                    ->where('sales.status', 'confirmed')
                    ->whereDate('sales.created_at', '>=', $startStr)
                    ->whereDate('sales.created_at', '<=', $endStr)
                    ->selectRaw('COALESCE(SUM(sale_items.quantity), 0)');
            }, 'units_sold')
            ->selectSub(function ($q) use ($startStr, $endStr) {
                $q->from('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->whereColumn('sale_items.variant_id', 'product_variants.id')
                    ->where('sales.status', 'confirmed')
                    ->whereDate('sales.created_at', '>=', $startStr)
                    ->whereDate('sales.created_at', '<=', $endStr)
                    ->selectRaw('COALESCE(SUM(sale_items.line_total), 0)');
            }, 'revenue')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderByDesc('units_sold')
            ->limit(10);
    }
}
