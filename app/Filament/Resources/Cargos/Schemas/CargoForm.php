<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cargos\Schemas;

use App\Models\ProductVariant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CargoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recebimento')
                    ->columns(3)
                    ->components([
                        TextInput::make('supplier')
                            ->label('Fornecedor')
                            ->placeholder('Opcional'),

                        DatePicker::make('received_at')
                            ->label('Data do recebimento')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpan(3),
                    ]),

                Section::make('Itens recebidos')
                    ->components([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('Itens')
                            ->minItems(1)
                            ->reorderable(false)
                            ->columns(12)
                            ->components([
                                Select::make('variant_id')
                                    ->label('Variação (SKU)')
                                    ->options(fn () => ProductVariant::query()
                                        ->with('product:id,name')
                                        ->orderBy('sku')
                                        ->get()
                                        ->mapWithKeys(fn ($v) => [
                                            $v->id => sprintf('%s — %s (%s)', $v->sku, optional($v->product)->name, $v->size),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(6),

                                TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('purchase_price')
                                    ->label('Preço de compra')
                                    ->prefix('R$')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required()
                                    ->columnSpan(2),

                                DatePicker::make('expires_at')
                                    ->label('Validade')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->columnSpan(2),
                            ])
                            ->addActionLabel('Adicionar item'),
                    ]),
            ]);
    }
}
