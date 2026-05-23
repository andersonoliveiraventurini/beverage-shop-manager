<?php

use App\Http\Controllers\HomeController;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Support\Facades\Route;

// Public storefront: the catalog (by category, with prices) plus depot contact
// info and a WhatsApp order CTA. The Filament admin tool lives under /admin.
Route::get('/', HomeController::class)->name('home');

// Printable receipt for an existing sale — A5 by default, 80mm via ?format=thermal.
// Behind auth so attendants / manager / deliverer can reprint; deliverer
// access is gated by the SalePolicy view rule.
Route::middleware('auth')->get('/sales/{sale}/receipt', function (Sale $sale, \Illuminate\Http\Request $request) {
    abort_unless(auth()->user()?->can('view', $sale), 403);

    return response()->view('sales.receipt', [
        'sale' => $sale->load(['customer', 'address', 'items.variant.product', 'user']),
        'store' => Store::current(),
        'format' => $request->query('format') === 'thermal' ? 'thermal' : 'a5',
    ]);
})->name('sales.receipt');
