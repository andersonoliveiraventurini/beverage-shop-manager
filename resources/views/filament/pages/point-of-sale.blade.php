<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_22rem]">
        {{-- LEFT: catalog --}}
        <section class="space-y-3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="/ buscar produto, marca ou SKU…"
                class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5"
            />

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('activeCategory', null)"
                    @class(['rounded-full px-3 py-1 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === null, 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== null])>
                    Todos
                </button>
                @foreach ($this->categories as $cat)
                    <button type="button" wire:click="$set('activeCategory', '{{ $cat['slug'] }}')"
                        @class(['rounded-full px-3 py-1 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === $cat['slug'], 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== $cat['slug']])>
                        {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                @forelse ($this->variants as $variant)
                    <button type="button" wire:key="variant-{{ $variant->id }}"
                        wire:click="addVariant({{ $variant->id }})"
                        class="group flex flex-col items-start rounded-xl border border-gray-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-primary-500 hover:shadow-md active:translate-y-0 dark:border-white/10 dark:bg-white/5">
                        <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $variant->product->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $variant->size }}</span>
                        @if ($variant->is_returnable)
                            <span class="mt-1 rounded bg-info-50 px-1.5 py-0.5 text-[10px] font-medium text-info-700 dark:bg-info-400/10 dark:text-info-300">↻ retornável</span>
                        @endif
                        <span class="fa-pos-num mt-2 text-base font-semibold text-primary-600 dark:text-primary-400">R$ {{ number_format((float) $variant->sale_price, 2, ',', '.') }}</span>
                    </button>
                @empty
                    <p class="col-span-full py-8 text-center text-sm text-gray-500">Nenhum produto encontrado.</p>
                @endforelse
            </div>
        </section>

        {{-- RIGHT: cart --}}
        <aside class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 lg:sticky lg:top-4 lg:h-fit">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">Comanda</h2>
                @if (filled($this->cart))
                    <button type="button" wire:click="$set('cart', [])" class="text-gray-400 hover:text-danger-500" title="Limpar comanda">
                        <x-filament::icon icon="heroicon-o-trash" class="h-5 w-5" />
                    </button>
                @endif
            </div>

            <div class="space-y-2">
                @forelse ($this->cart as $variantId => $line)
                    <div wire:key="cart-{{ $variantId }}" class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $line['size'] }}</p>
                            </div>
                            <span class="fa-pos-num text-sm font-semibold text-gray-950 dark:text-white">R$ {{ number_format($line['unit_price'] * $line['quantity'], 2, ',', '.') }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-white/10">
                                <button type="button" wire:click="decrement({{ $variantId }})" class="px-2 py-1 text-lg leading-none">−</button>
                                <span class="fa-pos-num w-8 text-center text-sm">{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="increment({{ $variantId }})" class="px-2 py-1 text-lg leading-none">+</button>
                            </div>
                            <button type="button" wire:click="removeVariant({{ $variantId }})" class="text-gray-400 hover:text-danger-500">
                                <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                            </button>
                        </div>

                        @if ($line['is_returnable'])
                            <div class="mt-2 space-y-1">
                                <select wire:change="setModality({{ $variantId }}, $event.target.value)" class="fi-select w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                                    <option value="exchange" @selected($line['modality'] === 'exchange')>Troca</option>
                                    <option value="full" @selected($line['modality'] === 'full')>Carga cheia</option>
                                    <option value="shell_only" @selected($line['modality'] === 'shell_only')>Somente casco</option>
                                </select>
                                @if ($line['modality'] === 'exchange')
                                    <input type="month" wire:change="setItemDate({{ $variantId }}, 'returned_shell_expires_at', $event.target.value)" class="fi-input w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Validade casco recebido" />
                                @endif
                                <input type="month" wire:change="setItemDate({{ $variantId }}, 'delivered_shell_expires_at', $event.target.value)" class="fi-input w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Validade casco entregue" />
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Comanda vazia. Clique num produto para adicionar.</p>
                @endforelse
            </div>

            @error('cart')
                <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
            @enderror

            <div class="mt-4 space-y-1 border-t border-gray-200 pt-3 text-sm dark:border-white/10">
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Subtotal</span><span class="fa-pos-num">R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span></div>
                @if ($this->type === 'delivery')
                    <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Entrega</span><span class="fa-pos-num">R$ {{ number_format((float) $this->delivery_fee, 2, ',', '.') }}</span></div>
                @endif
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Desconto</span><span class="fa-pos-num">R$ {{ number_format((float) $this->discount, 2, ',', '.') }}</span></div>
                <div class="mt-1 flex items-center justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                    <span class="text-base font-bold">TOTAL</span>
                    <span class="fa-pos-num text-2xl font-extrabold text-primary-600 dark:text-primary-400">R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                </div>
            </div>

            <x-filament::button type="button" wire:click="finalize" color="primary" size="lg" class="mt-3 w-full" :disabled="blank($this->cart)">
                Finalizar venda
            </x-filament::button>
        </aside>
    </div>
</x-filament-panels::page>
