<?php

declare(strict_types=1);

use App\Filament\Exports\SaleExporter;
use App\Filament\Pages\GeneralSales;
use App\Filament\Pages\WaterSales;
use App\Models\User;
use Filament\Actions\ExportAction;

beforeEach(function () {
    $this->actingAs(User::factory()->manager()->create());
});

it('WaterSales table has an export header action', function () {
    livewireTest(WaterSales::class)
        ->assertTableHeaderActionsExistInOrder(['export']);
});

it('GeneralSales table has an export header action', function () {
    livewireTest(GeneralSales::class)
        ->assertTableHeaderActionsExistInOrder(['export']);
});

it('SaleExporter defines the expected columns', function () {
    $columns = SaleExporter::getColumns();
    $names = array_map(fn ($col) => $col->getName(), $columns);

    expect($names)->toContain('created_at')
        ->toContain('id')
        ->toContain('customer.name')
        ->toContain('type')
        ->toContain('payment_method')
        ->toContain('total')
        ->toContain('status')
        ->toContain('contains_water');
});

it('SaleExporter columns carry the correct pt-BR labels', function () {
    $columns = collect(SaleExporter::getColumns())->keyBy(fn ($col) => $col->getName());

    expect($columns->get('created_at')->getLabel())->toBe('Data')
        ->and($columns->get('id')->getLabel())->toBe('Venda')
        ->and($columns->get('customer.name')->getLabel())->toBe('Cliente')
        ->and($columns->get('total')->getLabel())->toBe('Total')
        ->and($columns->get('status')->getLabel())->toBe('Status')
        ->and($columns->get('contains_water')->getLabel())->toBe('Contém água');
});

it('WaterSales ExportAction is wired to SaleExporter', function () {
    $table = livewireTest(WaterSales::class)->instance()->getTable();
    $headerActions = $table->getHeaderActions();

    $exportAction = collect($headerActions)->first(fn ($a) => $a instanceof ExportAction);

    expect($exportAction)->not->toBeNull()
        ->and($exportAction->getExporter())->toBe(SaleExporter::class);
});

it('GeneralSales ExportAction is wired to SaleExporter', function () {
    $table = livewireTest(GeneralSales::class)->instance()->getTable();
    $headerActions = $table->getHeaderActions();

    $exportAction = collect($headerActions)->first(fn ($a) => $a instanceof ExportAction);

    expect($exportAction)->not->toBeNull()
        ->and($exportAction->getExporter())->toBe(SaleExporter::class);
});
