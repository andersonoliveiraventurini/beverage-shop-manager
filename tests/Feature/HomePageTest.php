<?php

declare(strict_types=1);

use App\Models\Category;

it('renders the public homepage without authentication', function () {
    $cat = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $cat->products()->create(['name' => 'Galão Acqua Fina', 'brand' => 'Serra Negra', 'active' => true]);
    $product->variants()->create([
        'sku' => 'AGUA-ACQUAFINA-20L', 'size' => '20L', 'is_returnable' => true,
        'shell_cost' => 30, 'sale_price' => 15.00, 'min_stock' => 30,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('FA Distribuidora')
        ->assertSee('Galão Acqua Fina')
        ->assertSee('20L')
        ->assertDontSee('15,00')                   // sem preço: o valor é combinado no WhatsApp
        ->assertDontSee('R$')
        ->assertSee('data-carrinho-item="Galão Acqua Fina 20L (retornável)"', false)
        ->assertSee('data-whatsapp="5519981778284"', false)
        ->assertSee('wa.me/55', false)             // WhatsApp CTA link
        ->assertSee('Av. Transamazônica, 1197');   // depot address from config('brand')
});

it('lists only active categories and products', function () {
    $refri = Category::create(['slug' => 'refrigerante', 'name' => 'Refrigerante', 'active' => true]);
    $coca = $refri->products()->create(['name' => 'Coca-Cola', 'brand' => 'Coca-Cola', 'active' => true]);
    $coca->variants()->create(['sku' => 'REFRI-COCA-2L', 'size' => '2L', 'sale_price' => 13.00, 'min_stock' => 5]);

    // Inactive category must not appear.
    $cerveja = Category::create(['slug' => 'cerveja', 'name' => 'Cerveja', 'active' => false]);
    $brahma = $cerveja->products()->create(['name' => 'Brahma Chopp', 'brand' => 'Brahma', 'active' => true]);
    $brahma->variants()->create(['sku' => 'CERV-BRAHMA-LATA', 'size' => 'lata', 'sale_price' => 5.50, 'min_stock' => 5]);

    // Inactive product inside an active category must not appear.
    $skol = $refri->products()->create(['name' => 'Skol Descontinuada', 'brand' => 'Skol', 'active' => false]);
    $skol->variants()->create(['sku' => 'REFRI-SKOL-OLD', 'size' => 'lata', 'sale_price' => 4.00, 'min_stock' => 5]);

    $this->get('/')
        ->assertSee('Coca-Cola')
        ->assertDontSee('Brahma Chopp')
        ->assertDontSee('Skol Descontinuada');
});
