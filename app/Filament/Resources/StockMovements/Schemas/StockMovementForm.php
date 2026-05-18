<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ajuste manual de estoque')
                    ->description('Use este formulário apenas para ajustes manuais (entrada de carga, descarte, contagem). Movimentações geradas por vendas são automáticas.')
                    ->columns(3)
                    ->components([
                        Select::make('variant_id')
                            ->label('Variação (SKU)')
                            ->options(fn () => ProductVariant::query()
                                ->with('product:id,name')
                                ->orderBy('sku')
                                ->get()
                                ->mapWithKeys(fn ($v) => [$v->id => $v->sku . ' — ' . optional($v->product)->name . ' (' . $v->size . ')']))
                            ->searchable()
                            ->required()
                            ->columnSpan(2),

                        Select::make('direction')
                            ->label('Direção')
                            ->options([
                                StockMovement::DIRECTION_IN => 'Entrada (+)',
                                StockMovement::DIRECTION_OUT => 'Saída (−)',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('reason')
                            ->label('Motivo')
                            ->options([
                                StockMovement::REASON_MANUAL_ADJUST => 'Ajuste manual',
                                StockMovement::REASON_CARGO => 'Carga (entrada de fornecedor)',
                                StockMovement::REASON_WRITE_OFF => 'Baixa por vencimento',
                            ])
                            ->default(StockMovement::REASON_MANUAL_ADJUST)
                            ->required()
                            ->native(false),

                        TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
