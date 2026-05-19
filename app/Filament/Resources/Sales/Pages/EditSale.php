<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReceipt')
                ->label('Imprimir recibo')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('primary')
                ->url(fn () => route('sales.receipt', ['sale' => $this->record]))
                ->openUrlInNewTab(),

            Action::make('printThermal')
                ->label('Recibo 80mm')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('sales.receipt', ['sale' => $this->record, 'format' => 'thermal']))
                ->openUrlInNewTab(),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
