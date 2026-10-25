<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Public storefront: the active catalog grouped by category plus the depot's
     * contact info (from config('brand.*'), the documented source of truth) and a
     * pre-filled WhatsApp order link.
     */
    public function __invoke(): View
    {
        $categories = Category::query()
            ->where('active', true)
            ->whereHas('products', fn ($q) => $q->where('active', true))
            ->with(['products' => fn ($q) => $q
                ->where('active', true)
                ->orderBy('name')
                ->with(['variants' => fn ($v) => $v->orderBy('sale_price')])])
            // Água is the depot's main product, so it leads the page.
            ->orderByRaw("CASE WHEN slug = 'agua' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $whatsappDigits = preg_replace('/\D/', '', (string) config('brand.phones.whatsapp'));
        $whatsappUrl = 'https://wa.me/55'.$whatsappDigits
            .'?text='.rawurlencode('Olá! Gostaria de fazer um pedido na '.config('brand.name').'.');

        return view('home', [
            'categories' => $categories,
            'whatsappUrl' => $whatsappUrl,
            'whatsappNumber' => '55'.$whatsappDigits,
        ]);
    }
}
