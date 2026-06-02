<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Concerns\RederivesTrustedFees;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    use RederivesTrustedFees;

    protected static string $resource = SaleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->rederiveTrustedFees($data);
    }
}
