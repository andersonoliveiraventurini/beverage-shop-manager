<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Sale;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GeneralSales extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.general-sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Vendas gerais';

    protected static ?string $title = 'Vendas gerais';

    protected static string|\UnitEnum|null $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return optional(auth()->user())->isManager() || optional(auth()->user())->isAttendant();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Sale::query()->where('contains_water', false)->with('customer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('id')->label('Venda')->prefix('#')->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->placeholder('— balcão —')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'delivery' ? 'Entrega' : 'Balcão'),
                TextColumn::make('payment_method')->label('Pagamento')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cash' => 'Dinheiro', 'pix' => 'PIX', 'debit' => 'Débito', 'credit' => 'Crédito', default => $state,
                    }),
                TextColumn::make('total')->label('Total')->money('BRL')->alignEnd()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success', 'cancelled' => 'danger', default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('payment_method')->options([
                    'cash' => 'Dinheiro', 'pix' => 'PIX', 'debit' => 'Débito', 'credit' => 'Crédito',
                ]),
                SelectFilter::make('status')->options([
                    'open' => 'Aberta', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada',
                ]),
                Filter::make('today')
                    ->label('Apenas hoje')
                    ->query(fn ($q) => $q->whereDate('created_at', today())),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => \App\Filament\Resources\Sales\SaleResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
