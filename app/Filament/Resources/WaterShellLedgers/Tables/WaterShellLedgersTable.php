<?php

declare(strict_types=1);

namespace App\Filament\Resources\WaterShellLedgers\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WaterShellLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['customer', 'variant.product'])
                ->where('shell_count', '>', 0))
            ->defaultSort('expires_at')
            ->columns([
                TextColumn::make('expires_at')
                    ->label('Validade')
                    ->date('m/Y')
                    ->sortable()
                    ->weight('semibold')
                    ->color(fn ($state) => match (true) {
                        $state && $state->isPast() => 'danger',
                        $state && $state->lte(now()->addDays(30)) => 'danger',
                        $state && $state->lte(now()->addDays(60)) => 'warning',
                        $state && $state->lte(now()->addDays(90)) => 'info',
                        default => 'success',
                    })
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '—';
                        }
                        $diff = (int) $state->diffInDays(now()->startOfDay(), false);
                        $suffix = $state->isPast() ? sprintf(' (há %dd)', $diff) : sprintf(' (em %dd)', -$diff);
                        return $state->format('m/Y') . $suffix;
                    }),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('shell_count')
                    ->label('Cascos')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),

                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('variant.product.name')
                    ->label('Produto')
                    ->toggleable(),

                TextColumn::make('variant.size')
                    ->label('Tamanho')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('last_out_at')
                    ->label('Última saída')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('horizon')
                    ->label('Horizonte')
                    ->options([
                        '30' => 'Próximos 30 dias',
                        '60' => 'Próximos 60 dias',
                        '90' => 'Próximos 90 dias',
                        '180' => 'Próximos 180 dias',
                        '365' => 'Próximo ano',
                        'expired' => 'Apenas vencidos',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }
                        if ($value === 'expired') {
                            return $query->whereDate('expires_at', '<', now());
                        }
                        return $query
                            ->whereDate('expires_at', '>=', now())
                            ->whereDate('expires_at', '<=', now()->addDays((int) $value));
                    })
                    ->default('90')
                    ->native(false),

                SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('variant_id')
                    ->label('Variação')
                    ->relationship('variant', 'sku')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('open_customer')
                    ->label('Abrir cliente')
                    ->icon('heroicon-o-user')
                    ->url(fn ($record) => $record->customer_id
                        ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer_id])
                        : null),
            ])
            ->toolbarActions([]);
    }
}
