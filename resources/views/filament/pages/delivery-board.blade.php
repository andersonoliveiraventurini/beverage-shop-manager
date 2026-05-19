<x-filament-panels::page>
    <div wire:poll.{{ $this->pollSeconds }}s>
        <x-fa.wave-divider color="azul-cristal" />

        @php
            $pending = $this->getPendingDeliveries();
            $completed = $this->getCompletedTodayDeliveries();
        @endphp

        <section class="mt-6">
            <h2 class="text-lg font-bold mb-3" style="font-family: var(--font-display); color: var(--fa-azul-profundo);">
                Pendentes ({{ $pending->count() }})
            </h2>

            @if ($pending->isEmpty())
                <div class="rounded-lg border border-dashed p-6 text-center opacity-70">
                    Nenhuma entrega pendente no momento.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($pending as $delivery)
                        <div class="rounded-lg border p-4" style="background: var(--fa-branco-mineral); border-color: var(--fa-azul-profundo);">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-bold" style="font-family: var(--font-display);">
                                        Venda #{{ $delivery->sale_id }} —
                                        {{ optional($delivery->sale->customer)->name ?? '— sem cliente —' }}
                                    </div>
                                    <div class="text-sm opacity-80 mt-1">
                                        {{ optional($delivery->sale->address)->full_address ?? 'Endereço não definido' }}
                                    </div>
                                    <div class="text-sm mt-2">
                                        <span class="font-semibold">Total:</span>
                                        R$ {{ number_format((float) $delivery->sale->total, 2, ',', '.') }}
                                        ·
                                        <span class="font-semibold">Pagamento:</span> {{ Str::title($delivery->sale->payment_method) }}
                                    </div>
                                </div>
                                <div>
                                    @if ($delivery->status === \App\Models\Delivery::STATUS_EN_ROUTE)
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold"
                                              style="background: var(--fa-amarelo-solar); color: var(--fa-azul-profundo);">
                                            EM ROTA
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold border"
                                              style="border-color: var(--fa-azul-profundo); color: var(--fa-azul-profundo);">
                                            PENDENTE
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2 flex-wrap">
                                @if ($delivery->status === \App\Models\Delivery::STATUS_PENDING)
                                    <x-filament::button wire:click="startRoute({{ $delivery->id }})" color="primary">
                                        Iniciar rota
                                    </x-filament::button>
                                @endif
                                @if ($delivery->status === \App\Models\Delivery::STATUS_EN_ROUTE)
                                    <x-filament::button wire:click="markCompleted({{ $delivery->id }})" color="success">
                                        Marcar entregue
                                    </x-filament::button>
                                @endif
                                <x-filament::button
                                    wire:click="cancelDelivery({{ $delivery->id }}, 'Cancelada pelo entregador')"
                                    color="danger" outlined>
                                    Cancelar
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <x-fa.wave-divider color="amarelo-solar" class="mt-8" />

        <section class="mt-6">
            <h2 class="text-lg font-bold mb-3" style="font-family: var(--font-display); color: var(--fa-azul-profundo);">
                Concluídas hoje ({{ $completed->count() }})
            </h2>

            @if ($completed->isEmpty())
                <div class="rounded-lg border border-dashed p-6 text-center opacity-70">
                    Nenhuma entrega concluída hoje ainda.
                </div>
            @else
                <ul class="space-y-2">
                    @foreach ($completed as $delivery)
                        <li class="rounded border p-3 flex justify-between"
                            style="background: white; border-color: rgba(11, 61, 145, 0.2);">
                            <span>
                                #{{ $delivery->sale_id }} ·
                                {{ optional($delivery->sale->customer)->name ?? '—' }}
                                <span class="text-sm opacity-70">
                                    · {{ optional($delivery->completed_at)->format('H:i') }}
                                </span>
                            </span>
                            <span class="font-semibold">
                                R$ {{ number_format((float) $delivery->sale->total, 2, ',', '.') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>
