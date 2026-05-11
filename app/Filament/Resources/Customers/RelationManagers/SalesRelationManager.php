<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'Histórico de compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    public function form(Schema $schema): Schema
    {
        // History is read-mostly. Creation/edition happens in the full SaleResource.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('id')
                    ->label('Venda')
                    ->prefix('#')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'delivery' ? 'Entrega' : 'Balcão')
                    ->color(fn (string $state) => $state === 'delivery' ? 'info' : 'gray'),

                TextColumn::make('payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cash' => 'Dinheiro',
                        'pix' => 'PIX',
                        'debit' => 'Débito',
                        'credit' => 'Crédito',
                        default => $state,
                    }),

                TextColumn::make('items_count')
                    ->label('Itens')
                    ->numeric()
                    ->alignCenter(),

                IconColumn::make('contains_water')
                    ->label('Água')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->alignEnd()
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Aberta',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Pagamento')
                    ->options([
                        'cash' => 'Dinheiro',
                        'pix' => 'PIX',
                        'debit' => 'Débito',
                        'credit' => 'Crédito',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Aberta',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ]),
                TernaryFilter::make('contains_water')
                    ->label('Contém galão de água')
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nova venda')
                    ->url(fn () => SaleResource::getUrl('create', ['customer_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => SaleResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ]);
    }
}
