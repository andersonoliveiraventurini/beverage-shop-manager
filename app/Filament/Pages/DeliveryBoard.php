<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Delivery;
use App\Models\Sale;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class DeliveryBoard extends Page
{
    protected string $view = 'filament.pages.delivery-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Painel de entregas';

    protected static ?string $title = 'Painel de entregas';

    protected static string|\UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 3;

    /** Polling interval (seconds). Refresh implemented via wire:poll in the view. */
    public int $pollSeconds = 30;

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
        return $this->scopedQuery()
            ->whereIn('status', [Delivery::STATUS_PENDING, Delivery::STATUS_EN_ROUTE])
            ->orderBy('status')
            ->orderBy('created_at')
            ->get();
    }

    public function getCompletedTodayDeliveries()
    {
        return $this->scopedQuery()
            ->where('status', Delivery::STATUS_COMPLETED)
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->get();
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

        Notification::make()->title('Entrega concluída.')->success()->send();
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
