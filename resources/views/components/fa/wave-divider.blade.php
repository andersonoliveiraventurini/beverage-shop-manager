@props([
    'color' => 'azul-profundo',
    'inverted' => false,
    'height' => '16',
])

@php
    // Map prop to CSS variable. Keeping the map closed prevents accidental
    // arbitrary colors from sneaking in via templates. See docs/DESIGN.md §5.
    $token = match ($color) {
        'azul-profundo' => 'var(--fa-azul-profundo)',
        'azul-cristal' => 'var(--fa-azul-cristal)',
        'amarelo-solar' => 'var(--fa-amarelo-solar)',
        default => 'var(--fa-azul-profundo)',
    };
@endphp

<svg
    {{ $attributes->merge(['class' => 'fa-wave-divider w-full block', 'role' => 'presentation', 'aria-hidden' => 'true']) }}
    viewBox="0 0 1440 32"
    preserveAspectRatio="none"
    height="{{ $height }}"
    style="transform: {{ $inverted ? 'scaleY(-1)' : 'none' }}; fill: {{ $token }};"
>
    {{-- Single canonical wave path — used identically across admin, login, receipt, email. --}}
    <path d="M0 16 C 180 0, 360 32, 540 16 S 900 0, 1080 16 S 1440 32, 1440 16 L 1440 32 L 0 32 Z" />
</svg>
