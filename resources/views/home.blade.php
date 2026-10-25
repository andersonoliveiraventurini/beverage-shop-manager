@php
    $telMobile = preg_replace('/\D/', '', (string) config('brand.phones.mobile'));
    $telLandline = preg_replace('/\D/', '', (string) config('brand.phones.landline'));
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode((string) config('brand.address.one_line'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('brand.name') }} — {{ config('brand.tagline') }}</title>
    <meta name="description" content="{{ config('brand.name') }}: água mineral, bebidas, carvão e mais. {{ config('brand.address.one_line') }}. Peça pelo WhatsApp.">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Brand palette (azul-profundo #0B3D91, azul-cristal #3FA9F5, amarelo-solar #F7C948)
         is used inline below. This is a standalone page outside the Filament panel, so the
         hex lives here locally — keep synced with theme.css / docs/DESIGN.md §2. --}}
    <style>
        [x-cloak] { display: none !important; }
        .fa-blob { filter: blur(40px); }
        .fa-num { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    {{-- ===================== HERO ===================== --}}
    <header class="relative overflow-hidden bg-gradient-to-br from-[#0B3D91] via-[#15499f] to-[#3FA9F5] text-white">
        <div class="pointer-events-none absolute inset-0">
            <div class="fa-blob absolute -left-16 -top-16 h-64 w-64 rounded-full bg-[#3FA9F5]/40"></div>
            <div class="fa-blob absolute right-0 top-24 h-72 w-72 rounded-full bg-[#F7C948]/20"></div>
            <div class="fa-blob absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-white/10"></div>
        </div>

        <div class="relative mx-auto max-w-5xl px-5 py-14 sm:py-20">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#F7C948]">{{ config('brand.tagline') }}</p>
            <h1 class="mt-3 text-4xl font-extrabold leading-tight sm:text-6xl">{{ config('brand.name') }}</h1>
            <p class="mt-4 max-w-xl text-base text-white/85 sm:text-lg">
                Água mineral, refrigerantes, cervejas, carvão e itens de mercearia — entrega rápida e retirada no balcão.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-[#F7C948] px-7 py-3.5 text-base font-bold text-[#0B3D91] shadow-lg shadow-black/20 transition hover:-translate-y-0.5 hover:bg-[#ffd95e]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    Pedir pelo WhatsApp
                </a>
                <a href="#catalogo" class="inline-flex items-center justify-center rounded-full border border-white/40 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    Ver o catálogo
                </a>
            </div>

            <dl class="mt-10 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div class="flex items-start gap-2"><span aria-hidden="true">📍</span><div><dt class="font-semibold">Endereço</dt><dd class="text-white/80">{{ config('brand.address.one_line') }}</dd></div></div>
                <div class="flex items-start gap-2"><span aria-hidden="true">🕗</span><div><dt class="font-semibold">Horário</dt><dd class="text-white/80">{{ config('brand.hours.one_line') }}</dd></div></div>
                <div class="flex items-start gap-2"><span aria-hidden="true">📞</span><div><dt class="font-semibold">Telefone</dt><dd class="text-white/80"><a class="hover:underline" href="tel:+55{{ $telLandline }}">{{ config('brand.phones.landline') }}</a></dd></div></div>
            </dl>
        </div>
    </header>

    {{-- ===================== CATEGORY NAV ===================== --}}
    @if ($categories->isNotEmpty())
        <nav class="sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur" aria-label="Categorias">
            <div class="mx-auto flex max-w-5xl gap-2 overflow-x-auto px-5 py-3">
                @foreach ($categories as $category)
                    <a href="#cat-{{ $category->slug }}"
                       class="shrink-0 rounded-full bg-slate-100 px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-[#0B3D91] hover:text-white">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    {{-- ===================== CATALOG ===================== --}}
    {{-- Sem preços: o cliente monta o pedido (opcional) e manda pelo WhatsApp; o valor é combinado na conversa. --}}
    <main id="catalogo" class="mx-auto max-w-5xl space-y-12 px-5 py-12 pb-32">
        @if ($categories->isNotEmpty())
            <p class="rounded-2xl bg-[#F7C948]/20 px-5 py-4 text-base text-slate-800">
                Toque em <strong>+ Adicionar</strong> no que quiser e envie o pedido pelo WhatsApp. Se preferir, é só chamar no WhatsApp e pedir do seu jeito.
            </p>
        @endif
        @forelse ($categories as $category)
            <section id="cat-{{ $category->slug }}" class="scroll-mt-20">
                <h2 class="mb-5 flex items-center gap-3 text-2xl font-bold text-[#0B3D91]">
                    <span class="inline-block h-6 w-1.5 rounded-full bg-[#F7C948]"></span>
                    {{ $category->name }}
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($category->products as $product)
                        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                            <h3 class="text-base font-semibold text-slate-900">{{ $product->name }}</h3>
                            @if ($product->brand)
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $product->brand }}</p>
                            @endif

                            <ul class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                                @foreach ($product->variants as $variant)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-slate-600">
                                            {{ $variant->size }}
                                            @if ($variant->is_returnable)
                                                <span class="ml-1 rounded bg-[#3FA9F5]/10 px-1.5 py-0.5 text-[10px] font-medium text-[#1656b8]">↻ retornável</span>
                                            @endif
                                        </span>
                                        @php $item = trim($product->name . ' ' . $variant->size . ($variant->is_returnable ? ' (retornável)' : '')); @endphp
                                        <span data-carrinho-item="{{ $item }}" class="flex shrink-0 items-center gap-1">
                                            <button type="button" data-menos aria-label="Tirar 1 {{ $item }}" class="hidden h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-xl font-bold text-[#0B3D91]">−</button>
                                            <span data-qtd class="fa-num hidden w-7 text-center text-base font-bold text-[#0B3D91]">0</span>
                                            <button type="button" data-mais aria-label="Adicionar {{ $item }} ao pedido" class="flex h-10 min-w-10 items-center justify-center rounded-full bg-[#0B3D91] px-3 text-sm font-bold text-white">+ Adicionar</button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="py-16 text-center text-slate-500">Catálogo em atualização. Fale com a gente pelo WhatsApp.</p>
        @endforelse
    </main>

    {{-- ===================== DEPOT INFO ===================== --}}
    <section class="bg-[#0B3D91] text-white">
        <div class="mx-auto grid max-w-5xl gap-8 px-5 py-12 sm:grid-cols-2">
            <div>
                <h2 class="text-2xl font-bold">Venha até o depósito</h2>
                <p class="mt-3 text-white/85">{{ config('brand.address.one_line') }}</p>
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-[#F7C948] hover:underline">Ver no mapa →</a>

                <div class="mt-6 space-y-1 text-sm text-white/85">
                    <p><span class="font-semibold text-white">Seg–Sáb:</span> {{ config('brand.hours.weekday') }}</p>
                    <p><span class="font-semibold text-white">Dom/feriado:</span> {{ config('brand.hours.sunday') }}</p>
                </div>
            </div>

            <div class="sm:text-right">
                <h2 class="text-2xl font-bold">Fale com a gente</h2>
                <div class="mt-3 space-y-1 text-sm">
                    <p><a class="hover:underline" href="tel:+55{{ $telLandline }}">Fixo: {{ config('brand.phones.landline') }}</a></p>
                    <p><a class="hover:underline" href="tel:+55{{ $telMobile }}">Celular: {{ config('brand.phones.mobile') }}</a></p>
                </div>
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
                   class="mt-5 inline-flex items-center justify-center gap-2 rounded-full bg-[#25D366] px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#1fb957]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    Pedir pelo WhatsApp
                </a>
            </div>
        </div>
    </section>

    <footer class="bg-[#082c69] py-6 text-center text-xs text-white/60">
        © {{ date('Y') }} {{ config('brand.name') }} · {{ config('brand.tagline') }}
    </footer>

    {{-- Barra do pedido: aparece quando o cliente escolhe algum item e esconde o botão flutuante. --}}
    <div data-carrinho data-whatsapp="{{ $whatsappNumber }}" data-loja="{{ config('brand.name') }}" hidden
         class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white px-4 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.12)]">
        <div class="mx-auto flex max-w-5xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-base font-bold text-[#0B3D91]"><span data-total>0</span> <span data-total-rotulo>itens</span> no pedido</p>
                <button type="button" data-limpar class="text-sm text-slate-500 underline">Limpar pedido</button>
            </div>
            <a data-enviar href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
               class="inline-flex shrink-0 items-center justify-center rounded-full bg-[#25D366] px-5 py-3.5 text-base font-bold text-white shadow-lg">
                Enviar pelo WhatsApp
            </a>
        </div>
    </div>

    {{-- Floating WhatsApp --}}
    <a data-whatsapp-flutuante href="{{ $whatsappUrl }}" target="_blank" rel="noopener" aria-label="Pedir pelo WhatsApp"
       class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-xl transition hover:scale-105">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
    </a>
</body>
</html>
