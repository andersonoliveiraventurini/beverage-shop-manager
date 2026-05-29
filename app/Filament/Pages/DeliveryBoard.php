<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Delivery;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class DeliveryBoard extends Page
{
    protected string $view = 'filament.pages.delivery-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Painel de entregas';

    protected static ?string $title = 'Painel de entregas';

    protected static string|\UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 3;

    /** Polling interval (seconds). Refresh implemented via wire:poll in the view. */
    public int $pollSeconds = 30;

    /** Filter by district (customer_addresses.district). Empty string means no filter. */
    public string $districtFilter = '';

    /** Filter by status. Empty string means no filter (show pending + en_route). */
    public string $statusFilter = '';

    public function getTitle(): string|Htmlable
    {
        return static::$title;
    }

    public static function canAccess(): bool
    {
        return auth()->user() !== null;
    }

    public function getPendingDeliveries()
    {
        $query = $this->scopedQuery()
            ->when(
                $this->statusFilter !== '',
                fn ($q) => $q->where('deliveries.status', $this->statusFilter),
                fn ($q) => $q->whereIn('deliveries.status', [Delivery::STATUS_PENDING, Delivery::STATUS_EN_ROUTE]),
            )
            ->orderBy('deliveries.status')
            ->orderBy('deliveries.created_at');

        if ($this->districtFilter !== '') {
            $query->whereHas('sale.address', fn ($q) => $q->where('district', $this->districtFilter));
        }

        return $query->get();
    }

    public function getCompletedTodayDeliveries()
    {
        $query = $this->scopedQuery()
            ->where('deliveries.status', Delivery::STATUS_COMPLETED)
            ->whereDate('deliveries.completed_at', today())
            ->orderByDesc('deliveries.completed_at');

        if ($this->districtFilter !== '') {
            $query->whereHas('sale.address', fn ($q) => $q->where('district', $this->districtFilter));
        }

        return $query->get();
    }

    /**
     * Distinct district values from deliveries visible to the current user,
     * used to populate the district filter dropdown.
     *
     * @return Collection<int, string>
     */
    public function getDistrictOptions(): Collection
    {
        return $this->scopedQuery()
            ->join('sales', 'sales.id', '=', 'deliveries.sale_id')
            ->join('customer_addresses', 'customer_addresses.id', '=', 'sales.address_id')
            ->whereNotNull('customer_addresses.district')
            ->where('customer_addresses.district', '!=', '')
            ->orderBy('customer_addresses.district')
            ->pluck('customer_addresses.district')
            ->unique()
            ->values();
    }

    public function startRoute(int $deliveryId): void
    {
        $delivery = $this->findDelivery($deliveryId);
        $delivery->startRoute(auth()->user());

        Notification::make()->title('Entrega em rota.')->success()->send();
    }

    public function markCompleted(int $deliveryId): void
    {
        $delivery = $this->findDelivery($deliveryId);
        $delivery->markCompleted();

        Notification::make()->title('Entrega concluida.')->success()->send();
    }

    public function cancelDelivery(int $deliveryId, string $reason = ''): void
    {
        $delivery = $this->findDelivery($deliveryId);
        $delivery->cancel($reason ?: 'Cancelada pelo entregador.');

        Notification::make()->title('Entrega cancelada e venda revertida.')->warning()->send();
    }

    private function findDelivery(int $id): Delivery
    {
        $delivery = Delivery::with('sale.customer', 'sale.address')->findOrFail($id);
        abort_unless(auth()->user()?->can('update', $delivery), 403);

        return $delivery;
    }

    /**
     * Deliverers see only their own (or unassigned) deliveries; manager and
     * attendant see everyone's.
     */
    private function scopedQuery()
    {
        $user = auth()->user();
        $query = Delivery::query()->with('sale.customer', 'sale.address');

        if ($user && $user->isDeliverer()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('deliverer_id')->orWhere('deliverer_id', $user->id);
            });
        }

        return $query;
    }
}
