<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\ProductVariant;
use App\Models\SaleItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente e entrega')
                    ->columns(3)
                    ->components([
                        Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state): void {
                                if ($state === null) {
                                    $set('delivery_fee', 0);
                                    $set('building_fee', 0);
                                    $set('address_id', null);
                                    return;
                                }
                                $customer = \App\Models\Customer::with('primaryAddress')->find($state);
                                if (! $customer) {
                                    return;
                                }
                                $set('delivery_fee', (float) $customer->delivery_fee);
                                $set('building_fee', (float) $customer->building_fee);
                                $set('address_id', optional($customer->primaryAddress)->id);
                            })
                            ->helperText('Selecione um cliente para pré-preencher entrega, taxa e endereço (vendas de balcão podem ficar sem cliente).'),

                        Select::make('address_id')
                            ->label('Endereço de entrega')
                            ->options(function (Get $get) {
                                $customerId = $get('customer_id');
                                if (! $customerId) {
                                    return [];
                                }
                                return \App\Models\CustomerAddress::query()
                                    ->where('customer_id', $customerId)
                                    ->orderByDesc('is_primary')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [$a->id => $a->full_address])
                                    ->all();
                            })
                            ->searchable()
                            ->visible(fn (Get $get) => $get('type') === 'delivery'),

                        Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'counter' => 'Balcão',
                                'delivery' => 'Entrega',
                            ])
                            ->required()
                            ->default('counter')
                            ->live(),
                    ]),

                Section::make('Itens')
                    ->components([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('Itens da venda')
                            ->minItems(1)
                            ->reorderable(false)
                            ->columns(12)
                            ->itemLabel(fn (array $state) => self::itemLabel($state))
                            ->components([
                                Select::make('variant_id')
                                    ->label('Produto / variação')
                                    ->options(fn () => ProductVariant::query()
                                        ->with('product:id,name,brand')
                                        ->orderBy('sku')
                                        ->get()
                                        ->mapWithKeys(fn ($v) => [$v->id => sprintf(
                                            '%s — %s (%s)',
                                            $v->sku,
                                            optional($v->product)->name,
                                            $v->size,
                                        )])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if (! $state) {
                                            $set('unit_price', null);
                                            $set('modality', null);
                                            $set('returned_shell_expires_at', null);
                                            $set('delivered_shell_expires_at', null);
                                            return;
                                        }
                                        $variant = ProductVariant::find($state);
                                        if ($variant) {
                                            $set('unit_price', (float) $variant->sale_price);
                                        }
                                    })
                                    ->columnSpan(6),

                                TextInput::make('quantity')
                                    ->label('Qtd')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('unit_price')
                                    ->label('Preço unit.')
                                    ->prefix('R$')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required()
                                    ->columnSpan(4),

                                // --- Returnable-gallon modality + validities ---
                                Select::make('modality')
                                    ->label('Modalidade')
                                    ->options([
                                        SaleItem::MODALITY_FULL => 'Carga cheia (cliente leva conteúdo + casco)',
                                        SaleItem::MODALITY_EXCHANGE => 'Troca (cliente entrega casco vazio e leva cheio)',
                                        SaleItem::MODALITY_SHELL_ONLY => 'Somente casco (cliente leva casco vazio)',
                                    ])
                                    ->native(false)
                                    ->live()
                                    ->required(fn (Get $get) => self::isReturnable($get('variant_id')))
                                    ->visible(fn (Get $get) => self::isReturnable($get('variant_id')))
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if ($state !== SaleItem::MODALITY_EXCHANGE) {
                                            $set('returned_shell_expires_at', null);
                                        }
                                        if ($state === null) {
                                            $set('delivered_shell_expires_at', null);
                                        }
                                    })
                                    ->columnSpan(12),

                                DatePicker::make('returned_shell_expires_at')
                                    ->label('Validade do galão recebido (cliente entregou)')
                                    ->displayFormat('m/Y')
                                    ->format('Y-m-01')
                                    ->closeOnDateSelection()
                                    ->helperText('Mês/ano de validade impresso no casco que o cliente entregou.')
                                    ->required(fn (Get $get) => $get('modality') === SaleItem::MODALITY_EXCHANGE)
                                    ->visible(fn (Get $get) => self::isReturnable($get('variant_id'))
                                        && $get('modality') === SaleItem::MODALITY_EXCHANGE)
                                    ->columnSpan(6),

                                DatePicker::make('delivered_shell_expires_at')
                                    ->label('Validade do galão entregue (cliente levou)')
                                    ->displayFormat('m/Y')
                                    ->format('Y-m-01')
                                    ->closeOnDateSelection()
                                    ->helperText('Mês/ano de validade impresso no casco que o cliente levou.')
                                    ->required(fn (Get $get) => self::isReturnable($get('variant_id'))
                                        && in_array($get('modality'), [
                                            SaleItem::MODALITY_FULL,
                                            SaleItem::MODALITY_EXCHANGE,
                                            SaleItem::MODALITY_SHELL_ONLY,
                                        ], true))
                                    ->visible(fn (Get $get) => self::isReturnable($get('variant_id'))
                                        && in_array($get('modality'), [
                                            SaleItem::MODALITY_FULL,
                                            SaleItem::MODALITY_EXCHANGE,
                                            SaleItem::MODALITY_SHELL_ONLY,
                                        ], true))
                                    ->columnSpan(6),
                            ])
                            ->addActionLabel('Adicionar item'),
                    ]),

                Section::make('Pagamento e taxas')
                    ->columns(3)
                    ->components([
                        Select::make('payment_method')
                            ->label('Forma de pagamento')
                            ->options([
                                'cash' => 'Dinheiro',
                                'pix' => 'PIX',
                                'debit' => 'Cartão de débito',
                                'credit' => 'Cartão de crédito',
                            ])
                            ->required()
                            ->native(false)
                            ->live(),

                        TextInput::make('card_fee')
                            ->label('Taxa de cartão')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->visible(fn (Get $get) => in_array($get('payment_method'), ['debit', 'credit'], true)),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Aberta',
                                'confirmed' => 'Confirmada',
                                'cancelled' => 'Cancelada',
                            ])
                            ->required()
                            ->default('open')
                            ->native(false),

                        TextInput::make('delivery_fee')
                            ->label('Taxa de entrega')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->visible(fn (Get $get) => $get('type') === 'delivery'),

                        TextInput::make('building_fee')
                            ->label('Adicional prédio')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->visible(fn (Get $get) => $get('type') === 'delivery'),

                        TextInput::make('out_of_area_override')
                            ->label('Adicional fora-da-área')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->helperText('Apenas para clientes fora da área.')
                            ->visible(fn (Get $get) => $get('type') === 'delivery'),

                        TextInput::make('discount')
                            ->label('Desconto')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->live(),

                        TextInput::make('discount_reason')
                            ->label('Motivo do desconto')
                            ->maxLength(180)
                            ->visible(fn (Get $get) => (float) $get('discount') > 0)
                            ->required(fn (Get $get) => (float) $get('discount') > 0)
                            ->columnSpan(2),
                    ]),

                Section::make('Observações')
                    ->collapsed()
                    ->components([
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function isReturnable(?int $variantId): bool
    {
        if (! $variantId) {
            return false;
        }
        return (bool) ProductVariant::query()
            ->whereKey($variantId)
            ->value('is_returnable');
    }

    private static function itemLabel(array $state): ?string
    {
        $variantId = $state['variant_id'] ?? null;
        if (! $variantId) {
            return null;
        }
        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant) {
            return null;
        }
        $qty = $state['quantity'] ?? 1;
        return sprintf('%d × %s (%s)', (int) $qty, optional($variant->product)->name, $variant->size);
    }
}
