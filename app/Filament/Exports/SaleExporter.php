<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SaleExporter extends Exporter
{
    protected static ?string $model = Sale::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')
                ->label('Data')
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : ''),
            ExportColumn::make('id')
                ->label('Venda'),
            ExportColumn::make('customer.name')
                ->label('Cliente'),
            ExportColumn::make('type')
                ->label('Tipo')
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'delivery' => 'Entrega',
                    'counter' => 'Balcão',
                    default => $state ?? '',
                }),
            ExportColumn::make('payment_method')
                ->label('Pagamento')
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'cash' => 'Dinheiro',
                    'pix' => 'PIX',
                    'debit' => 'Débito',
                    'credit' => 'Crédito',
                    default => $state ?? '',
                }),
            ExportColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'open' => 'Aberta',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                    default => $state ?? '',
                }),
            ExportColumn::make('contains_water')
                ->label('Contém água')
                ->formatStateUsing(fn ($state) => $state ? 'Sim' : 'Não'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Exportação de vendas concluída: '.Number::format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).' exportadas.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam.';
        }

        return $body;
    }
}
