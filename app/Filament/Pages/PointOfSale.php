<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Ponto de Venda (PDV) — a fast counter screen. The attendant adds products in
 * one click from a searchable, category-tabbed catalog grid and sees a live
 * cart + running total. On finalize it persists a normal Sale + SaleItems and
 * relies entirely on the existing model hooks (SaleItem::saved →
 * Sale::recalculate() → Sale::saved → settleStock() + shell-ledger sync). No
 * domain logic is reimplemented here.
 *
 * Design: docs/superpowers/specs/2026-05-23-pos-counter-sales-design.md
 */
class PointOfSale extends Page
{
    protected string $view = 'filament.pages.point-of-sale';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    protected static ?string $navigationLabel = 'PDV';

    protected static ?string $title = 'Ponto de Venda';

    protected static string|\UnitEnum|null $navigationGroup = 'Operação';

    // Sort 0: the PDV is the primary day-to-day tool, so it leads the group.
    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pos';

    // --- Catalog filters ---
    public string $search = '';

    public ?string $activeCategory = null;

    // --- Cart (keyed by variant_id) + sale meta ---
    /** @var array<int, array<string, mixed>> */
    public array $cart = [];

    public string $type = 'counter';

    public ?int $customer_id = null;

    public ?int $address_id = null;

    public string $payment_method = 'cash';

    public float $delivery_fee = 0;

    public float $building_fee = 0;

    public float $discount = 0;

    public ?string $discount_reason = null;

    public static function canAccess(): bool
    {
        return optional(auth()->user())->isManager() || optional(auth()->user())->isAttendant();
    }

    /* -----------------------------------------------------------------------
     | Catalog (computed)
     * -------------------------------------------------------------------- */

    /** @return Collection<int, array{slug:string,name:string}> */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name]);
    }

    /** @return Collection<int, ProductVariant> */
    #[Computed]
    public function variants(): Collection
    {
        return ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->with(['product:id,name,brand,category_id', 'product.category:id,slug,name'])
            ->when($this->activeCategory, fn ($q) => $q->whereHas(
                'product.category',
                fn ($c) => $c->where('slug', $this->activeCategory),
            ))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn ($w) => $w
                    ->where('sku', 'like', $term)
                    ->orWhereHas('product', fn ($p) => $p
                        ->where('name', 'like', $term)
                        ->orWhere('brand', 'like', $term)));
            })
            ->orderBy('sku')
            ->get();
    }

    /* -----------------------------------------------------------------------
     | Cart mutations
     * -------------------------------------------------------------------- */

    public function addVariant(int $variantId): void
    {
        if (isset($this->cart[$variantId])) {
            $this->cart[$variantId]['quantity']++;

            return;
        }

        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant) {
            return;
        }

        $this->cart[$variantId] = [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => optional($variant->product)->name ?? $variant->sku,
            'size' => $variant->size,
            'is_returnable' => (bool) $variant->is_returnable,
            'quantity' => 1,
            'unit_price' => (float) $variant->sale_price,
            // Default returnable water to Troca (exchange) per the design.
            'modality' => $variant->is_returnable ? SaleItem::MODALITY_EXCHANGE : null,
            'returned_shell_expires_at' => null,
            'delivered_shell_expires_at' => null,
        ];
    }

    public function increment(int $variantId): void
    {
        if (isset($this->cart[$variantId])) {
            $this->cart[$variantId]['quantity']++;
        }
    }

    public function decrement(int $variantId): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        $this->cart[$variantId]['quantity']--;

        if ($this->cart[$variantId]['quantity'] <= 0) {
            unset($this->cart[$variantId]);
        }
    }

    public function removeVariant(int $variantId): void
    {
        unset($this->cart[$variantId]);
    }

    public function setModality(int $variantId, string $modality): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        $this->cart[$variantId]['modality'] = $modality;

        // The returned-shell validity only applies to an exchange.
        if ($modality !== SaleItem::MODALITY_EXCHANGE) {
            $this->cart[$variantId]['returned_shell_expires_at'] = null;
        }
    }

    public function setItemDate(int $variantId, string $field, ?string $date): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        if (! in_array($field, ['returned_shell_expires_at', 'delivered_shell_expires_at'], true)) {
            return;
        }

        // Normalize to the first of the month (matches SaleForm's 'Y-m-01').
        $this->cart[$variantId][$field] = $date ? substr($date, 0, 7) . '-01' : null;
    }

    public function setUnitPrice(int $variantId, float $price): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        if (! optional(auth()->user())->isManager()) {
            return; // Only managers may override the price (mirrors SaleForm).
        }

        $this->cart[$variantId]['unit_price'] = max(0, $price);
    }

    /* -----------------------------------------------------------------------
     | Totals (computed)
     * -------------------------------------------------------------------- */

    #[Computed]
    public function subtotal(): float
    {
        return round(collect($this->cart)->sum(
            fn (array $i) => (float) $i['unit_price'] * (int) $i['quantity'],
        ), 2);
    }

    #[Computed]
    public function total(): float
    {
        $fees = $this->type === 'delivery'
            ? (float) $this->delivery_fee + (float) $this->building_fee
            : 0.0;

        return round(max(0, $this->subtotal + $fees - (float) $this->discount), 2);
    }

    /* -----------------------------------------------------------------------
     | Finalize
     * -------------------------------------------------------------------- */

    public function finalize(): void
    {
        $this->validateCart();

        DB::transaction(function (): void {
            $sale = Sale::create([
                'customer_id' => $this->type === 'delivery' ? $this->customer_id : null,
                'address_id' => $this->type === 'delivery' ? $this->address_id : null,
                'user_id' => auth()->id(),
                'type' => $this->type,
                'payment_method' => $this->payment_method,
                'status' => Sale::STATUS_CONFIRMED,
                'delivery_fee' => $this->type === 'delivery' ? (float) $this->delivery_fee : 0,
                'building_fee' => $this->type === 'delivery' ? (float) $this->building_fee : 0,
                'discount' => (float) $this->discount,
                'discount_reason' => (float) $this->discount > 0 ? $this->discount_reason : null,
                'paid_at' => now(),
            ]);

            foreach ($this->cart as $entry) {
                $sale->items()->create([
                    'variant_id' => $entry['variant_id'],
                    'quantity' => (int) $entry['quantity'],
                    'unit_price' => (float) $entry['unit_price'],
                    'modality' => $entry['modality'],
                    'returned_shell_expires_at' => $entry['returned_shell_expires_at'],
                    'delivered_shell_expires_at' => $entry['delivered_shell_expires_at'],
                ]);
            }
        });

        $this->reset(['cart', 'discount', 'discount_reason']);

        Notification::make()->title('Venda registrada com sucesso')->success()->send();
    }

    protected function validateCart(): void
    {
        if ($this->cart === []) {
            throw ValidationException::withMessages(['cart' => 'Adicione ao menos um item à comanda.']);
        }

        foreach ($this->cart as $entry) {
            if (! $entry['is_returnable']) {
                continue;
            }

            // Returnable gallon rules mirror SaleForm: the delivered-shell
            // validity is always required; the returned-shell validity is
            // required on exchange.
            if (empty($entry['delivered_shell_expires_at'])) {
                throw ValidationException::withMessages([
                    'cart' => "Informe a validade do casco entregue para {$entry['name']} ({$entry['size']}).",
                ]);
            }

            if ($entry['modality'] === SaleItem::MODALITY_EXCHANGE && empty($entry['returned_shell_expires_at'])) {
                throw ValidationException::withMessages([
                    'cart' => "Informe a validade do casco recebido para {$entry['name']} ({$entry['size']}).",
                ]);
            }
        }

        if ($this->type === 'delivery' && (! $this->customer_id || ! $this->address_id)) {
            throw ValidationException::withMessages(['cart' => 'Entregas exigem cliente e endereço.']);
        }
    }
}
