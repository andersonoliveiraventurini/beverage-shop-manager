@props([
    'compact' => false,
])

{{-- Hero Disk Entregas block from the brand manual (page 04/05). Uses Solar
     Yellow background + Azul Profundo display type. Used on the receipt and
     anywhere else the customer-facing surface needs the phone CTA. --}}
<div {{ $attributes->merge([
    'class' => 'fa-disk-entregas rounded-lg p-4 text-center',
    'style' => 'background-color: var(--fa-amarelo-solar); color: var(--fa-azul-profundo); font-family: var(--font-display);',
]) }}>
    <div style="font-family: var(--font-sans); font-weight: 800; letter-spacing: 0.18em; font-size: 0.75rem;">
        DISK ENTREGAS
    </div>
    <div style="font-size: {{ $compact ? '1.5rem' : '2rem' }}; line-height: 1; margin-top: 0.25rem;">
        {{ config('brand.phones.landline') }}
    </div>
    <div style="font-size: {{ $compact ? '1.5rem' : '2rem' }}; line-height: 1; margin-top: 0.25rem;">
        {{ config('brand.phones.mobile') }} <span style="font-size: 0.8em;">✓ WhatsApp</span>
    </div>
</div>
