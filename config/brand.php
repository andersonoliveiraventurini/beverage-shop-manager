<?php

declare(strict_types=1);

/*
 * FA Distribuidora — brand string literals.
 *
 * Single source of truth for the depot's identifying information (name,
 * tagline, address, hours, phones). Pull via config('brand.*') in Blade
 * templates, emails, receipts and printable views. See docs/DESIGN.md §7.
 *
 * Never copy-paste these values elsewhere — a future change must flow from
 * here to every surface in one edit.
 */

return [
    'name' => 'FA Distribuidora',

    'tagline' => 'ÁGUA · BEBIDAS · CARVÃO',

    'address' => [
        'street' => 'Av. Transamazônica, 1197',
        'district' => 'Jardim Garcia',
        'city' => 'Campinas',
        'state' => 'SP',
        'one_line' => 'Av. Transamazônica, 1197 · Jardim Garcia · Campinas–SP',
    ],

    'hours' => [
        'weekday' => 'Seg–Sáb 08–18h',
        'sunday' => 'Dom/feriado 08–12h',
        'one_line' => 'Seg–Sáb 08–18h · Dom/feriado 08–12h',
    ],

    'phones' => [
        'landline' => '(19) 3326-7690',
        'mobile' => '(19) 98177-8284',
        'whatsapp' => '(19) 98177-8284',
    ],
];
