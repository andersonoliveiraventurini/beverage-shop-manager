<?php

declare(strict_types=1);

/*
 * Panel-wide E2E sweep — the "does every screen run?" gate.
 *
 * Goal (per the E2E routine spec): walk every resource controller, mount every
 * route (list / create / edit / view), submit every create form, and assert no
 * screen throws an execution error (500 / ViewException / unhandled exception).
 *
 * This complements the behaviour-focused per-resource tests: those assert WHAT
 * a screen does; this asserts that NONE of them blow up when rendered or
 * submitted. New resources should be added to the datasets below so the sweep
 * keeps pace with the panel.
 *
 * Access matrix (locked by tests/Feature/Policies/RoleAccessMatrixTest.php):
 *   - manager:   every resource + Settings + DepotConfig
 *   - attendant: viewAny on all resources; create only Customer + Sale
 *   - deliverer: viewAny only on Sales; DeliveryBoard (any authed user)
 */

use App\Filament\Pages\DeliveryBoard;
use App\Filament\Pages\DepotConfig;
use App\Filament\Pages\GeneralSales;
use App\Filament\Pages\Settings;
use App\Filament\Pages\WaterSales;
use App\Filament\Resources\Cargos\Pages\CreateCargo;
use App\Filament\Resources\Cargos\Pages\ListCargos;
use App\Filament\Resources\Cargos\Pages\ViewCargo;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PhonesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\SalesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\WaterShellsRelationManager;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\StockMovements\Pages\CreateStockMovement;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\WaterShellLedgers\Pages\ListWaterShellLedgers;
use App\Models\Cargo;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Pages\Dashboard;

/* ---------------------------------------------------------------------------
 | Fixture helpers (e2e* prefix avoids collisions with other test files)
 * ------------------------------------------------------------------------- */

/** Water category + product + returnable 20L variant, optionally pre-stocked. */
function e2eWaterVariant(int $stock = 100): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L E2E ' . uniqid(), 'active' => true]);
    $variant = $product->variants()->create([
        'sku' => 'E2E-' . strtoupper(uniqid()),
        'size' => '20L',
        'is_returnable' => true,
        'shell_cost' => 30,
        'sale_price' => 15.00,
        'min_stock' => 10,
    ]);

    if ($stock > 0) {
        StockMovement::create([
            'variant_id' => $variant->id,
            'direction' => StockMovement::DIRECTION_IN,
            'reason' => StockMovement::REASON_MANUAL_ADJUST,
            'quantity' => $stock,
        ]);
    }

    return $variant->refresh();
}

/** A persisted sale with one returnable item, for edit-page rendering. */
function e2eSale(string $status = 'open'): Sale
{
    $variant = e2eWaterVariant();
    $customer = Customer::factory()->create(['delivery_fee' => 2.00]);

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => $status,
        'delivery_fee' => 2.00,
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    return $sale->fresh();
}

/** A persisted cargo with one item, for the view page. */
function e2eCargo(): Cargo
{
    $variant = e2eWaterVariant(0);
    $cargo = Cargo::create(['supplier' => 'Fornecedor E2E', 'received_at' => now()->toDateString()]);
    $cargo->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 20,
        'purchase_price' => 7.50,
        'expires_at' => now()->addDays(60)->toDateString(),
    ]);

    return $cargo->fresh();
}

/* ===========================================================================
 | 1. Route render sweep — every screen mounts without an execution error
 * ========================================================================= */

describe('route render sweep (manager)', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->manager()->create()));

    it('renders the Dashboard with every widget mounted', function () {
        livewireTest(Dashboard::class)->assertSuccessful();
    });

    it('renders every resource list page', function (string $page) {
        livewireTest($page)->assertSuccessful();
    })->with([
        'Clientes'        => ListCustomers::class,
        'Vendas'          => ListSales::class,
        'Produtos'        => ListProducts::class,
        'Categorias'      => ListCategories::class,
        'Movimentações'   => ListStockMovements::class,
        'Cargas'          => ListCargos::class,
    ]);

    it('renders the Vencimentos list page (water-shell tracking on)', function () {
        DeliverySetting::current()->update(['track_water_shells' => true]);
        livewireTest(ListWaterShellLedgers::class)->assertSuccessful();
    });

    it('renders every resource create page', function (string $page) {
        livewireTest($page)->assertSuccessful();
    })->with([
        'CreateCustomer'      => CreateCustomer::class,
        'CreateSale'          => CreateSale::class,
        'CreateProduct'       => CreateProduct::class,
        'CreateCategory'      => CreateCategory::class,
        'CreateStockMovement' => CreateStockMovement::class,
        'CreateCargo'         => CreateCargo::class,
    ]);

    it('renders the Customer edit page', function () {
        $customer = Customer::factory()->create();
        livewireTest(EditCustomer::class, ['record' => $customer->getRouteKey()])->assertSuccessful();
    });

    it('renders the Category edit page', function () {
        $category = Category::create(['slug' => 'refrigerantes', 'name' => 'Refrigerantes', 'active' => true]);
        livewireTest(EditCategory::class, ['record' => $category->getRouteKey()])->assertSuccessful();
    });

    it('renders the Product edit page', function () {
        $variant = e2eWaterVariant(0);
        livewireTest(EditProduct::class, ['record' => $variant->product->getRouteKey()])->assertSuccessful();
    });

    it('renders the Sale edit page', function () {
        $sale = e2eSale();
        livewireTest(EditSale::class, ['record' => $sale->getRouteKey()])->assertSuccessful();
    });

    it('renders the Cargo view page', function () {
        $cargo = e2eCargo();
        livewireTest(ViewCargo::class, ['record' => $cargo->getRouteKey()])->assertSuccessful();
    });

    it('renders every Customer relation manager', function (string $relationManager) {
        DeliverySetting::current()->update(['track_water_shells' => true]); // gates WaterShells RM
        $customer = Customer::factory()->create();

        livewireTest($relationManager, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])->assertSuccessful();
    })->with([
        'Phones'      => PhonesRelationManager::class,
        'Addresses'   => AddressesRelationManager::class,
        'Sales'       => SalesRelationManager::class,
        'WaterShells' => WaterShellsRelationManager::class,
    ]);

    it('renders the Product variants relation manager', function () {
        $variant = e2eWaterVariant(0);

        livewireTest(VariantsRelationManager::class, [
            'ownerRecord' => $variant->product,
            'pageClass' => EditProduct::class,
        ])->assertSuccessful();
    });

    it('renders every custom operational + reporting page', function (string $page) {
        livewireTest($page)->assertSuccessful();
    })->with([
        'DeliveryBoard' => DeliveryBoard::class,
        'WaterSales'    => WaterSales::class,
        'GeneralSales'  => GeneralSales::class,
        'Settings'      => Settings::class,
        'DepotConfig'   => DepotConfig::class,
    ]);
});

/* ===========================================================================
 | 2. Form submission sweep — every create form persists with no form errors
 * ========================================================================= */

describe('form submission sweep (manager)', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->manager()->create()));

    it('submits the Category create form', function () {
        livewireTest(CreateCategory::class)
            ->fillForm(['name' => 'Carvão', 'slug' => 'carvao', 'active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::where('slug', 'carvao')->exists())->toBeTrue();
    });

    it('submits the Product create form', function () {
        $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);

        livewireTest(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Galão 20L Sweep',
                'brand' => 'Sweep',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Product::where('name', 'Galão 20L Sweep')->exists())->toBeTrue();
    });

    it('submits the StockMovement create form (manual stock-in)', function () {
        $variant = e2eWaterVariant(0);

        livewireTest(CreateStockMovement::class)
            ->fillForm([
                'variant_id' => $variant->id,
                'direction' => StockMovement::DIRECTION_IN,
                'reason' => StockMovement::REASON_MANUAL_ADJUST,
                'quantity' => 25,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect($variant->refresh()->current_stock)->toBe(25);
    });

    it('submits the Cargo create form and generates stock', function () {
        $variant = e2eWaterVariant(0);

        livewireTest(CreateCargo::class)
            ->fillForm([
                'supplier' => 'Sweep Supplier',
                'received_at' => now()->toDateString(),
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 40,
                    'purchase_price' => 7.50,
                    'expires_at' => now()->addDays(45)->toDateString(),
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect($variant->refresh()->current_stock)->toBe(40);
    });

    it('submits the Customer create form', function () {
        livewireTest(CreateCustomer::class)
            ->fillForm([
                'name' => 'Maria Sweep',
                'in_delivery_area' => true,
                'delivery_fee' => 3.00,
                'building_fee' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Customer::where('name', 'Maria Sweep')->exists())->toBeTrue();
    });

    it('submits the Sale create form (counter cash sale)', function () {
        $variant = e2eWaterVariant();

        livewireTest(CreateSale::class)
            ->fillForm([
                'type' => 'counter',
                'payment_method' => 'cash',
                'status' => 'confirmed',
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => 15.00,
                    'modality' => SaleItem::MODALITY_FULL,
                    'delivered_shell_expires_at' => '2030-03-01',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect((float) Sale::query()->latest('id')->first()->total)->toBe(30.00);
    });

    it('submits the Settings form (delivery defaults)', function () {
        livewireTest(Settings::class)
            ->fillForm([
                'radius_km' => 3.5,
                'default_delivery_fee' => 4.00,
                'out_of_area_extra_fee' => 2.00,
                'default_building_fee' => 1.50,
                'track_water_shells' => true,
                'near_expiry_threshold_days' => 30,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect((float) DeliverySetting::current()->fresh()->default_delivery_fee)->toBe(4.00);
    });

    it('submits the DepotConfig form', function () {
        livewireTest(DepotConfig::class)
            ->fillForm([
                'name' => 'FA Distribuidora — Sweep',
                'street' => 'Av. Transamazônica',
                'number' => '1197',
                'district' => 'Jardim Garcia',
                'city' => 'Campinas',
                'state' => 'SP',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });
});

/* ===========================================================================
 | 3. Per-role render pass — allowed screens run without execution errors
 * ========================================================================= */

describe('per-role render pass', function () {
    it('lets an attendant render every screen they may reach', function (string $page) {
        $this->actingAs(User::factory()->attendant()->create());
        livewireTest($page)->assertSuccessful();
    })->with([
        'ListCustomers'      => ListCustomers::class,
        'ListSales'          => ListSales::class,
        'ListProducts'       => ListProducts::class,
        'ListCategories'     => ListCategories::class,
        'ListStockMovements' => ListStockMovements::class,
        'CreateCustomer'     => CreateCustomer::class,
        'CreateSale'         => CreateSale::class,
        'WaterSales'         => WaterSales::class,
        'GeneralSales'       => GeneralSales::class,
        'DeliveryBoard'      => DeliveryBoard::class,
    ]);

    it('lets a deliverer render the Sales list and the delivery board', function (string $page) {
        $this->actingAs(User::factory()->deliverer()->create());
        livewireTest($page)->assertSuccessful();
    })->with([
        'ListSales'     => ListSales::class,
        'DeliveryBoard' => DeliveryBoard::class,
    ]);
});
