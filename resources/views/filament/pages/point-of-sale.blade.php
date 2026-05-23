<x-filament-panels::page>
    <div
        x-data="{
            showFinalize: false,
            onKey(e) {
                const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
                if (e.key === '/' && ! typing) { e.preventDefault(); this.$refs.search?.focus(); }
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); this.showFinalize = true; }
            },
        }"
        @keydown.window="onKey($event)"
        @pos-finalized.window="showFinalize = false"
    >
        {{-- Toolbar: counter / delivery toggle + (delivery) customer & address --}}
        <div class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-white/10">
                <button type="button" wire:click="setType('counter')"
                    @class(['rounded-md px-4 py-2 text-sm font-semibold transition', 'bg-primary-600 text-white' => $this->type === 'counter', 'text-gray-600 dark:text-gray-300' => $this->type !== 'counter'])>
                    Balcão
                </button>
                <button type="button" wire:click="setType('delivery')"
                    @class(['rounded-md px-4 py-2 text-sm font-semibold transition', 'bg-primary-600 text-white' => $this->type === 'delivery', 'text-gray-600 dark:text-gray-300' => $this->type !== 'delivery'])>
                    Entrega
                </button>
            </div>

            @if ($this->type === 'delivery')
                <div class="relative flex-1 sm:max-w-md">
                    @if ($this->selectedCustomer)
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $this->selectedCustomer->name }}</p>
                                @if ($this->addressOptions->isNotEmpty())
                                    <select wire:model.live="address_id" class="fi-select mt-1 w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                                        @foreach ($this->addressOptions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <p class="text-xs text-danger-600">Cliente sem endereço cadastrado.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="setType('delivery')" wire:click.prevent="$set('customer_id', null)" class="shrink-0 text-xs text-gray-400 hover:text-danger-500">trocar</button>
                        </div>
                    @else
                        <input type="text" wire:model.live.debounce.300ms="customerSearch"
                            placeholder="Buscar cliente por nome ou telefone…"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5" />
                        @if ($this->customerResults->isNotEmpty())
                            <ul class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900">
                                @foreach ($this->customerResults as $c)
                                    <li>
                                        <button type="button" wire:click="selectCustomer({{ $c['id'] }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5">{{ $c['name'] }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_22rem]">
            {{-- LEFT: catalog --}}
            <section class="space-y-3">
                <input
                    type="text"
                    x-ref="search"
                    wire:model.live.debounce.300ms="search"
                    wire:keydown.enter="addFirstMatch"
                    placeholder="/ buscar produto, marca ou SKU…"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5"
                />

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('activeCategory', null)"
                        @class(['rounded-full px-3 py-1.5 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === null, 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== null])>
                        Todos
                    </button>
                    @foreach ($this->categories as $cat)
                        <button type="button" wire:click="$set('activeCategory', '{{ $cat['slug'] }}')"
                            @class(['rounded-full px-3 py-1.5 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === $cat['slug'], 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== $cat['slug']])>
                            {{ $cat['name'] }}
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                    @forelse ($this->variants as $variant)
                        <button type="button" wire:key="variant-{{ $variant->id }}"
                            wire:click="addVariant({{ $variant->id }})"
                            class="group flex min-h-[6rem] select-none flex-col items-start rounded-xl border border-gray-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-primary-500 hover:shadow-md active:translate-y-0 dark:border-white/10 dark:bg-white/5">
                            <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $variant->product->name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $variant->size }}</span>
                            @if ($variant->is_returnable)
                                <span class="mt-1 rounded bg-info-50 px-1.5 py-0.5 text-[10px] font-medium text-info-700 dark:bg-info-400/10 dark:text-info-300">↻ retornável</span>
                            @endif
                            <span class="fa-pos-num mt-auto pt-2 text-base font-semibold text-primary-600 dark:text-primary-400">R$ {{ number_format((float) $variant->sale_price, 2, ',', '.') }}</span>
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
                                    <button type="button" wire:click="decrement({{ $variantId }})" class="px-3 py-1.5 text-lg leading-none">−</button>
                                    <span class="fa-pos-num w-8 text-center text-sm">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="increment({{ $variantId }})" class="px-3 py-1.5 text-lg leading-none">+</button>
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

                <x-filament::button type="button" x-on:click="showFinalize = true" color="primary" size="lg" class="mt-3 w-full" :disabled="blank($this->cart)">
                    Finalizar venda
                </x-filament::button>
            </aside>
        </div>

        {{-- Finalize modal: payment method + cash tendered/change --}}
        <div x-show="showFinalize" x-cloak x-transition.opacity
            class="fixed inset-0 z-40 flex items-center justify-center bg-gray-950/50 p-4"
            x-on:click.self="showFinalize = false" x-on:keydown.escape.window="showFinalize = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900" wire:key="finalize-modal">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">Finalizar venda</h2>
                    <button type="button" x-on:click="showFinalize = false" class="text-gray-400 hover:text-gray-600">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <p class="mb-1 text-sm text-gray-500">Forma de pagamento</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['cash' => 'Dinheiro', 'pix' => 'PIX', 'debit' => 'Débito', 'credit' => 'Crédito'] as $method => $label)
                        <button type="button" wire:click="setPaymentMethod('{{ $method }}')"
                            @class(['rounded-lg border px-3 py-2.5 text-sm font-medium transition', 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300' => $this->payment_method === $method, 'border-gray-200 dark:border-white/10' => $this->payment_method !== $method])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if ($this->payment_method === 'cash')
                    <div class="mt-4 space-y-2">
                        <label class="text-sm text-gray-500">Valor recebido</label>
                        <input type="number" step="0.01" min="0" wire:model.live="cashTendered"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5" />
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Troco</span>
                            <span class="fa-pos-num font-semibold @if ($this->changeDue > 0) text-success-600 @endif">R$ {{ number_format($this->changeDue, 2, ',', '.') }}</span>
                        </div>
                    </div>
                @endif

                <div class="mt-4 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                    <span class="text-sm font-bold">TOTAL</span>
                    <span class="fa-pos-num text-xl font-extrabold text-primary-600 dark:text-primary-400">R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                </div>

                @error('cart')
                    <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                @enderror

                <x-filament::button type="button" wire:click="finalize" color="primary" size="lg" class="mt-4 w-full">
                    Confirmar venda
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
