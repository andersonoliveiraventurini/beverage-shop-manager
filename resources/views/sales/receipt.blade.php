@php
    $isThermal = $format === 'thermal';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo · Venda #{{ $sale->id }} · {{ config('brand.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --fa-azul-profundo: #0B3D91;
            --fa-azul-cristal:  #3FA9F5;
            --fa-amarelo-solar: #F7C948;
            --fa-branco-mineral: #FAFBFD;
            --fa-tinta-noite: #0E1730;
            --font-display: 'Archivo Black', 'Inter', sans-serif;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        @import url('https://fonts.bunny.net/css?family=archivo-black:400|inter:400,600,800');
        * { box-sizing: border-box; }
        body {
            font-family: var(--font-sans);
            color: var(--fa-tinta-noite);
            background: var(--fa-branco-mineral);
            margin: 0;
            padding: {{ $isThermal ? '8px 4px' : '24px' }};
            font-size: {{ $isThermal ? '11px' : '13px' }};
        }
        .receipt {
            max-width: {{ $isThermal ? '76mm' : '148mm' }};
            margin: 0 auto;
            background: #FFFFFF;
            padding: 16px;
            border-radius: 8px;
        }
        h1, h2, h3 { font-family: var(--font-display); margin: 0; letter-spacing: -0.01em; }
        h1 { font-size: {{ $isThermal ? '16px' : '24px' }}; color: var(--fa-azul-profundo); }
        h2 { font-size: {{ $isThermal ? '12px' : '16px' }}; }
        .tagline {
            font-weight: 800;
            letter-spacing: 0.18em;
            font-size: {{ $isThermal ? '9px' : '11px' }};
            color: var(--fa-azul-profundo);
            margin-top: 2px;
        }
        .meta { color: var(--fa-tinta-noite); opacity: 0.8; }
        table.items { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.items th, table.items td {
            text-align: left;
            padding: 4px 2px;
            border-bottom: 1px dashed #ccc;
        }
        table.items td.qty, table.items td.price, table.items td.total { text-align: right; }
        .totals { margin-top: 8px; }
        .totals .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .totals .row.total { font-family: var(--font-display); font-size: {{ $isThermal ? '14px' : '18px' }}; color: var(--fa-azul-profundo); padding-top: 6px; border-top: 2px solid var(--fa-azul-profundo); }
        .wave { display: block; width: 100%; height: 12px; margin: 8px 0; }
        .footer { margin-top: 16px; text-align: center; opacity: 0.85; }
        @media print {
            body { padding: 0; }
            .receipt { padding: 8px; border-radius: 0; box-shadow: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="receipt">
    <header style="text-align: center;">
        <h1>{{ $store->name }}</h1>
        <div class="tagline">{{ config('brand.tagline') }}</div>
        <div class="meta" style="margin-top: 6px;">{{ $store->full_address }}</div>
        <div class="meta">{{ $store->hours ?? config('brand.hours.one_line') }}</div>
    </header>

    <svg class="wave" viewBox="0 0 1440 32" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 16 C 180 0, 360 32, 540 16 S 900 0, 1080 16 S 1440 32, 1440 16 L 1440 32 L 0 32 Z" fill="var(--fa-azul-profundo)"/>
    </svg>

    <section>
        <h2>Venda #{{ $sale->id }}</h2>
        <div class="meta">{{ $sale->created_at?->format('d/m/Y H:i') }} · {{ $sale->type === 'delivery' ? 'Entrega' : 'Balcão' }} · Pagamento: {{ Str::title($sale->payment_method) }}</div>
        @if ($sale->customer)
            <div class="meta">Cliente: {{ $sale->customer->name }}</div>
        @endif
        @if ($sale->type === 'delivery' && $sale->address)
            <div class="meta">Entrega em: {{ $sale->address->full_address }}</div>
        @endif
    </section>

    <table class="items">
        <thead>
        <tr>
            <th>Item</th>
            <th class="qty">Qtd</th>
            <th class="price">Unit.</th>
            <th class="total">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->items as $item)
            <tr>
                <td>
                    {{ optional($item->variant?->product)->name ?? '—' }}
                    <span class="meta">({{ optional($item->variant)->size }})</span>
                    @if ($item->modality)
                        <span class="meta"> · {{ Str::title($item->modality) }}</span>
                    @endif
                </td>
                <td class="qty">{{ $item->quantity }}</td>
                <td class="price">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                <td class="total">R$ {{ number_format((float) $item->line_total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Subtotal</span><span>R$ {{ number_format((float) $sale->subtotal, 2, ',', '.') }}</span></div>
        @if ((float) $sale->delivery_fee > 0)
            <div class="row"><span>Entrega</span><span>R$ {{ number_format((float) $sale->delivery_fee, 2, ',', '.') }}</span></div>
        @endif
        @if ((float) ($sale->out_of_area_override ?? 0) > 0)
            <div class="row"><span>Fora-da-área</span><span>R$ {{ number_format((float) $sale->out_of_area_override, 2, ',', '.') }}</span></div>
        @endif
        @if ((float) $sale->building_fee > 0)
            <div class="row"><span>Prédio</span><span>R$ {{ number_format((float) $sale->building_fee, 2, ',', '.') }}</span></div>
        @endif
        @if ((float) $sale->card_fee > 0)
            <div class="row"><span>Taxa de cartão</span><span>R$ {{ number_format((float) $sale->card_fee, 2, ',', '.') }}</span></div>
        @endif
        @if ((float) $sale->discount > 0)
            <div class="row"><span>Desconto{{ $sale->discount_reason ? " ({$sale->discount_reason})" : '' }}</span><span>− R$ {{ number_format((float) $sale->discount, 2, ',', '.') }}</span></div>
        @endif
        <div class="row total"><span>Total</span><span>R$ {{ number_format((float) $sale->total, 2, ',', '.') }}</span></div>
    </div>

    <svg class="wave" viewBox="0 0 1440 32" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 16 C 180 0, 360 32, 540 16 S 900 0, 1080 16 S 1440 32, 1440 16 L 1440 32 L 0 32 Z" fill="var(--fa-azul-cristal)"/>
    </svg>

    <x-fa.disk-entregas :compact="$isThermal" />

    <div class="footer meta">
        Obrigado pela preferência! · {{ now()->format('d/m/Y H:i') }}
        @if ($sale->user)
            <br><small>Atendente: {{ $sale->user->name }}</small>
        @endif
    </div>

    <div class="no-print" style="text-align: center; margin-top: 16px;">
        <button onclick="window.print()" style="background: var(--fa-azul-profundo); color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-family: var(--font-sans); font-weight: 600;">
            Imprimir
        </button>
    </div>
</div>
</body>
</html>
