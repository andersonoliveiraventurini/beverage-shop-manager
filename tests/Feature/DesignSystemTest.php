<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * NFR-01 (PRD) — Brand Compliance verification gate.
 *
 * Two checks:
 * 1. config/brand.php exposes every key documented in docs/DESIGN.md §7.
 * 2. No hex color literal appears outside the allow-list — design tokens may
 *    only live in theme.css, AdminPanelProvider, SVG logos and the docs.
 * 3. <x-fa.wave-divider /> renders and consumes one of the brand color tokens.
 *
 * If this test starts failing, do NOT silence it — either move the new value
 * into the allowed sources or read it via config('brand.*') / CSS variables.
 */

it('exposes every brand string literal documented in DESIGN.md', function () {
    $brand = config('brand');

    expect($brand)->toBeArray()
        ->and($brand['name'])->toBe('FA Distribuidora')
        ->and($brand['tagline'])->toBe('ÁGUA · BEBIDAS · CARVÃO')
        ->and($brand['address']['one_line'])->toContain('Av. Transamazônica, 1197')
        ->and($brand['address']['city'])->toBe('Campinas')
        ->and($brand['hours']['one_line'])->toContain('Seg–Sáb')
        ->and($brand['phones']['landline'])->toBe('(19) 3326-7690')
        ->and($brand['phones']['mobile'])->toBe('(19) 98177-8284')
        ->and($brand['phones']['whatsapp'])->toBe('(19) 98177-8284');
});

it('does not leak hex color literals outside the allow-listed source files', function () {
    $root = base_path();

    // Allow-listed files: the design-token source-of-truth + the brand SVGs +
    // the docs. Everything else must consume tokens via CSS variables, the
    // `--fa-*` custom properties, the Filament Color::hex bindings, or
    // Tailwind palette classes.
    $allowList = [
        'resources/css/filament/admin/theme.css',
        'app/Providers/Filament/AdminPanelProvider.php',
        'public/images/fa-logo.svg',
        'public/images/fa-logo-mark.svg',
        'public/favicon.svg',
        // This very test references token values in strings — exempt itself.
        'tests/Feature/DesignSystemTest.php',
    ];

    $scanDirs = ['app', 'config', 'resources/views', 'resources/css', 'resources/js'];
    $offenders = [];

    foreach ($scanDirs as $dir) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (! is_dir($abs)) {
            continue;
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['php', 'blade', 'css', 'js', 'svg'], true)
                && ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = ltrim(str_replace([$root . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname()), '/');
            if (in_array($rel, $allowList, true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            // Match #RGB, #RRGGBB, #RRGGBBAA — three sizes used in CSS / SVG.
            if (preg_match_all('/#[0-9A-Fa-f]{3}\b|#[0-9A-Fa-f]{6}\b|#[0-9A-Fa-f]{8}\b/', $contents, $m)) {
                $offenders[$rel] = array_values(array_unique($m[0]));
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Hex color literals must live in the allow-listed brand source files only. '
        . 'Move the value into resources/css/filament/admin/theme.css as a CSS variable, '
        . 'or — for Filament panel colors — into AdminPanelProvider with a matching comment. '
        . "Offenders:\n" . json_encode($offenders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
});

it('renders the wave-divider Blade component with a brand color token', function () {
    $html = Blade::render('<x-fa.wave-divider color="amarelo-solar" />');

    expect($html)
        ->toContain('fa-wave-divider')
        ->toContain('var(--fa-amarelo-solar)')
        ->toContain('viewBox="0 0 1440 32"');
});

it('flips the wave when inverted is set', function () {
    $html = Blade::render('<x-fa.wave-divider inverted />');

    expect($html)->toContain('scaleY(-1)');
});

it('defaults to Azul Profundo when no color prop is given', function () {
    $html = Blade::render('<x-fa.wave-divider />');

    expect($html)->toContain('var(--fa-azul-profundo)');
});
