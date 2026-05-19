<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use App\Models\SaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsTable extends TableWidget
{
    protected static ?string $heading = 'Top 10 produtos do mês';

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
        // Top products of the current month, ranked by units sold across all
        // confirmed sales. We aggregate sale_items + join through variants/
        // products so the table can show readable names.
        return ProductVariant::query()
            ->select([
                'product_variants.id',
                'product_variants.sku',
                'product_variants.size',
                'products.name as product_name',
            ])
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->whereColumn('sale_items.variant_id', 'product_variants.id')
                    ->where('sales.status', 'confirmed')
                    ->whereMonth('sales.created_at', now()->month)
                    ->whereYear('sales.created_at', now()->year)
                    ->selectRaw('COALESCE(SUM(sale_items.quantity), 0)');
            }, 'units_sold')
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->whereColumn('sale_items.variant_id', 'product_variants.id')
                    ->where('sales.status', 'confirmed')
                    ->whereMonth('sales.created_at', now()->month)
                    ->whereYear('sales.created_at', now()->year)
                    ->selectRaw('COALESCE(SUM(sale_items.line_total), 0)');
            }, 'revenue')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderByDesc('units_sold')
            ->limit(10);
    }
}
