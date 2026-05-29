<?php

use App\Http\Controllers\DashboardReportController;
use App\Http\Controllers\HomeController;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public storefront: the catalog (by category, with prices) plus depot contact
// info and a WhatsApp order CTA. The Filament admin tool lives under /admin.
Route::get('/', HomeController::class)->name('home');

// Named alias so Laravel's 'auth' middleware can redirect unauthenticated
// requests to the Filament admin login (the only login surface in this app).
Route::redirect('/login', '/admin/login')->name('login');

// Printable receipt for an existing sale — A5 by default, 80mm via ?format=thermal.
// Behind auth so attendants / manager / deliverer can reprint; deliverer
// access is gated by the SalePolicy view rule.
Route::middleware('auth')->get('/sales/{sale}/receipt', function (Sale $sale, Request $request) {
    abort_unless(auth()->user()?->can('view', $sale), 403);

    return response()->view('sales.receipt', [
        'sale' => $sale->load(['customer', 'address', 'items.variant.product', 'user']),
        'store' => Store::current(),
        'format' => $request->query('format') === 'thermal' ? 'thermal' : 'a5',
    ]);
})->name('sales.receipt');

// PDF export of the manager dashboard KPI summary.
// Gated behind auth + manager-only check (abort_unless inside the controller).
// Mirrors the sales.receipt pattern (same 'auth' middleware, same abort_unless style).
Route::middleware('auth')->get('/admin/dashboard/report.pdf', DashboardReportController::class)
    ->name('dashboard.report.pdf');
