<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Carga inicial do catálogo a partir do levantamento físico do depósito.
 *
 * Estrutura: Category → Product → ProductVariant
 *
 * Convenções:
 * - Apenas galões de água 10L e 20L são retornáveis (is_returnable = true).
 * - Preços de venda lidos da tabela manuscrita do depósito (foto da lista
 *   fixada no balcão) e das etiquetas visíveis nas fotos dos produtos.
 * - cost_price estimado em ~65% do sale_price (substituir pela carga real).
 * - SKU pattern: <CAT>-<MARCA>-<TAM>-<EXTRA?> em maiúsculas.
 *
 * Idempotente: pode ser executado múltiplas vezes (firstOrCreate por slug/SKU).
 */
final class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $categoryData) {
            $category = Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                ['name' => $categoryData['name'], 'active' => true],
            );

            foreach ($categoryData['products'] as $productData) {
                $product = Product::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'brand' => $productData['brand'] ?? null,
                    ],
                    [
                        'description' => $productData['description'] ?? null,
                        'active' => true,
                    ],
                );

                foreach ($productData['variants'] as $variant) {
                    ProductVariant::updateOrCreate(
                        ['sku' => $variant['sku']],
                        [
                            'product_id' => $product->id,
                            'size' => $variant['size'],
                            'is_returnable' => $variant['is_returnable'] ?? false,
                            'shell_cost' => $variant['shell_cost'] ?? null,
                            'sale_price' => $variant['sale_price'],
                            'cost_price' => $variant['cost_price'] ?? round($variant['sale_price'] * 0.65, 2),
                            'min_stock' => $variant['min_stock'] ?? 5,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array{slug: string, name: string, products: array<int, array<string, mixed>>}>
     */
    private function catalog(): array
    {
        return [
            // ============================================================
            // ÁGUA — produto principal do depósito
            // ============================================================
            [
                'slug' => 'agua',
                'name' => 'Água',
                'products' => [
                    [
                        'name' => 'Galão de Água Mineral Acqua Fina',
                        'brand' => 'Serra Negra',
                        'description' => 'Galão retornável (Mineradora Santa Maria) - validade 3 anos',
                        'variants' => [
                            ['sku' => 'AGUA-ACQUAFINA-20L', 'size' => '20L', 'is_returnable' => true, 'shell_cost' => 30.00, 'sale_price' => 15.00, 'min_stock' => 30],
                            ['sku' => 'AGUA-ACQUAFINA-10L', 'size' => '10L', 'is_returnable' => true, 'shell_cost' => 30.00, 'sale_price' => 10.00, 'min_stock' => 20],
                        ],
                    ],
                    [
                        'name' => 'Garrafão de Água Mineral Lindóya Vida',
                        'brand' => 'Lindóya Vida',
                        'description' => 'Fonte Ágata, sem gás, garrafão descartável (validade 12 meses)',
                        'variants' => [
                            ['sku' => 'AGUA-LINDOYA-6L', 'size' => '6L', 'sale_price' => 8.00, 'min_stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Água Mineral em Garrafa',
                        'brand' => 'Lindóya Vida',
                        'variants' => [
                            ['sku' => 'AGUA-LINDOYA-1500ML', 'size' => '1,5L', 'sale_price' => 4.00, 'min_stock' => 24],
                            ['sku' => 'AGUA-LINDOYA-500ML', 'size' => '500ml', 'sale_price' => 3.00, 'min_stock' => 24],
                        ],
                    ],
                    [
                        'name' => 'Água Mineral em Garrafa',
                        'brand' => 'Levíssima',
                        'variants' => [
                            ['sku' => 'AGUA-LEVISSIMA-510ML', 'size' => '510ml', 'sale_price' => 3.00, 'min_stock' => 24],
                        ],
                    ],
                    [
                        'name' => 'Água Mineral em Garrafa',
                        'brand' => 'Bioleve',
                        'variants' => [
                            ['sku' => 'AGUA-BIOLEVE-1500ML', 'size' => '1,5L', 'sale_price' => 4.00, 'min_stock' => 24],
                        ],
                    ],
                    [
                        'name' => 'Água Mineral em Garrafa',
                        'brand' => 'Svíssima',
                        'variants' => [
                            ['sku' => 'AGUA-SVISSIMA-500ML', 'size' => '500ml', 'sale_price' => 3.00, 'min_stock' => 24],
                        ],
                    ],
                ],
            ],

            // ============================================================
            // REFRIGERANTES
            // ============================================================
            [
                'slug' => 'refrigerante',
                'name' => 'Refrigerante',
                'products' => [
                    [
                        'name' => 'Coca-Cola Original',
                        'brand' => 'Coca-Cola',
                        'variants' => [
                            ['sku' => 'REFRI-COCA-200ML', 'size' => '200ml', 'sale_price' => 3.50],
                            ['sku' => 'REFRI-COCA-350ML-LATA', 'size' => '350ml lata', 'sale_price' => 6.00],
                            ['sku' => 'REFRI-COCA-2L', 'size' => '2L', 'sale_price' => 13.00, 'min_stock' => 30],
                            ['sku' => 'REFRI-COCA-2L-RET', 'size' => '2L retornável', 'sale_price' => 11.00, 'min_stock' => 30],
                        ],
                    ],
                    [
                        'name' => 'Coca-Cola Zero Açúcar',
                        'brand' => 'Coca-Cola',
                        'variants' => [
                            ['sku' => 'REFRI-COCAZERO-350ML-LATA', 'size' => '350ml lata', 'sale_price' => 6.00],
                            ['sku' => 'REFRI-COCAZERO-2L', 'size' => '2L', 'sale_price' => 13.00],
                        ],
                    ],
                    [
                        // NOVO produto - tabela manuscrita: "Coca dieta" R$ 9,00
                        'name' => 'Coca-Cola Diet',
                        'brand' => 'Coca-Cola',
                        'description' => 'Versão dietética da Coca-Cola',
                        'variants' => [
                            ['sku' => 'REFRI-COCADIET-1L', 'size' => '1L', 'sale_price' => 9.00, 'min_stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Guaraná Antarctica',
                        'brand' => 'Antarctica',
                        'variants' => [
                            ['sku' => 'REFRI-GUARANA-350ML-LATA', 'size' => '350ml lata', 'sale_price' => 5.00],
                            ['sku' => 'REFRI-GUARANA-1L', 'size' => '1L', 'sale_price' => 7.00],
                            ['sku' => 'REFRI-GUARANA-2L', 'size' => '2L', 'sale_price' => 11.00, 'min_stock' => 24],
                        ],
                    ],
                    [
                        'name' => 'Guaraná Antarctica Zero',
                        'brand' => 'Antarctica',
                        'variants' => [['sku' => 'REFRI-GUARANAZERO-2L', 'size' => '2L', 'sale_price' => 11.00]],
                    ],
                    [
                        'name' => 'Fanta Laranja',
                        'brand' => 'Coca-Cola',
                        'variants' => [
                            ['sku' => 'REFRI-FANTALAR-200ML', 'size' => '200ml', 'sale_price' => 3.50],
                            ['sku' => 'REFRI-FANTALAR-2L', 'size' => '2L', 'sale_price' => 11.00],
                        ],
                    ],
                    [
                        'name' => 'Fanta Uva',
                        'brand' => 'Coca-Cola',
                        'variants' => [
                            ['sku' => 'REFRI-FANTAUVA-200ML', 'size' => '200ml', 'sale_price' => 3.50],
                            ['sku' => 'REFRI-FANTAUVA-2L-RET', 'size' => '2L retornável', 'sale_price' => 10.00],
                        ],
                    ],
                    [
                        'name' => 'Sprite',
                        'brand' => 'Coca-Cola',
                        'variants' => [['sku' => 'REFRI-SPRITE-200ML', 'size' => '200ml', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Schweppes Tônica',
                        'brand' => 'Coca-Cola',
                        'variants' => [['sku' => 'REFRI-SCHWEPPES-1500ML', 'size' => '1,5L', 'sale_price' => 8.50]],
                    ],
                    [
                        'name' => 'Refrigerante Mogi Maçã',
                        'brand' => 'Mogi',
                        'variants' => [['sku' => 'REFRI-MOGIMACA-2L', 'size' => '2L', 'sale_price' => 7.00]],
                    ],
                    [
                        'name' => 'Refrigerante Mogi Guaraná',
                        'brand' => 'Mogi',
                        'variants' => [['sku' => 'REFRI-MOGIGUARANA-2L', 'size' => '2L', 'sale_price' => 7.00]],
                    ],
                    [
                        'name' => 'Refrigerante Mogi Soda Limonada',
                        'brand' => 'Mogi',
                        'variants' => [['sku' => 'REFRI-MOGISODA-2L', 'size' => '2L', 'sale_price' => 7.00]],
                    ],
                    [
                        'name' => 'Refrigerante Mogi Abacaxi',
                        'brand' => 'Mogi',
                        'variants' => [['sku' => 'REFRI-MOGIABACAXI-2L', 'size' => '2L', 'sale_price' => 7.00]],
                    ],
                ],
            ],

            // ============================================================
            // CERVEJA — long neck (vidro) e lata
            // ============================================================
            [
                'slug' => 'cerveja',
                'name' => 'Cerveja',
                'products' => [
                    [
                        'name' => 'Brahma Chopp',
                        'brand' => 'Brahma',
                        'variants' => [
                            ['sku' => 'CERV-BRAHMA-LN-355ML', 'size' => '355ml long neck', 'sale_price' => 7.00, 'min_stock' => 48],
                            ['sku' => 'CERV-BRAHMA-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 5.50, 'min_stock' => 48],
                        ],
                    ],
                    [
                        'name' => 'Brahma Malzbier',
                        'brand' => 'Brahma',
                        'variants' => [['sku' => 'CERV-MALZBIER-350ML', 'size' => '350ml lata', 'sale_price' => 6.50]],
                    ],
                    [
                        'name' => 'Brahma Zero Álcool',
                        'brand' => 'Brahma',
                        'variants' => [['sku' => 'CERV-BRAHMAZERO-350ML', 'size' => '350ml lata', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Heineken Original',
                        'brand' => 'Heineken',
                        'variants' => [
                            ['sku' => 'CERV-HEINEKEN-LN-330ML', 'size' => '330ml long neck', 'sale_price' => 9.00, 'min_stock' => 24],
                            ['sku' => 'CERV-HEINEKEN-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 7.50, 'min_stock' => 24],
                        ],
                    ],
                    [
                        'name' => 'Spaten Munich',
                        'brand' => 'Spaten',
                        'description' => 'Cerveja puro malte 5,2% — caixa com 12 latas de 350ml',
                        'variants' => [['sku' => 'CERV-SPATEN-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 7.00]],
                    ],
                    [
                        'name' => 'Budweiser',
                        'brand' => 'Budweiser',
                        'variants' => [
                            ['sku' => 'CERV-BUD-LN-330ML', 'size' => '330ml long neck', 'sale_price' => 8.00],
                            ['sku' => 'CERV-BUD-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 6.50],
                        ],
                    ],
                    [
                        'name' => 'Antarctica Original',
                        'brand' => 'Antarctica',
                        'variants' => [
                            ['sku' => 'CERV-ANTARCTICA-LN-355ML', 'size' => '355ml long neck', 'sale_price' => 7.00],
                            ['sku' => 'CERV-ANTARCTICA-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 5.50],
                        ],
                    ],
                    [
                        'name' => 'Antarctica Pilsen',
                        'brand' => 'Antarctica',
                        'variants' => [
                            ['sku' => 'CERV-ANTPILSEN-LN-355ML', 'size' => '355ml long neck', 'sale_price' => 7.00],
                            ['sku' => 'CERV-ANTPILSEN-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 5.50],
                        ],
                    ],
                    [
                        'name' => 'Skol',
                        'brand' => 'Skol',
                        'variants' => [['sku' => 'CERV-SKOL-LATA-350ML', 'size' => '350ml lata', 'sale_price' => 5.50]],
                    ],
                ],
            ],

            // ============================================================
            // VINHO
            // ============================================================
            [
                'slug' => 'vinho',
                'name' => 'Vinho',
                'products' => [
                    [
                        'name' => 'Vinho de Mesa Tinto Suave Bordô',
                        'brand' => 'Cantina Agrícola',
                        'variants' => [['sku' => 'VINHO-CANTINA-1L', 'size' => '1L', 'sale_price' => 18.00, 'min_stock' => 6]],
                    ],
                ],
            ],

            // ============================================================
            // SUCO
            // ============================================================
            [
                'slug' => 'suco',
                'name' => 'Suco',
                'products' => [
                    [
                        'name' => 'Del Valle Frut Uva',
                        'brand' => 'Del Valle',
                        'variants' => [
                            ['sku' => 'SUCO-DELVALLE-UVA-450ML', 'size' => '450ml', 'sale_price' => 5.00],
                            ['sku' => 'SUCO-DELVALLE-UVA-1L', 'size' => '1L', 'sale_price' => 8.50],
                        ],
                    ],
                    [
                        'name' => 'Del Valle Frut Laranja',
                        'brand' => 'Del Valle',
                        'variants' => [
                            ['sku' => 'SUCO-DELVALLE-LAR-450ML', 'size' => '450ml', 'sale_price' => 5.00],
                            ['sku' => 'SUCO-DELVALLE-LAR-1L', 'size' => '1L', 'sale_price' => 8.50],
                        ],
                    ],
                    [
                        'name' => 'Tang Suco em Pó',
                        'brand' => 'Tang',
                        'description' => '100% da recomendação diária de Vitaminas C e D + Zinco',
                        'variants' => [
                            ['sku' => 'SUCO-TANG-LAR-18G', 'size' => '18g (laranja)', 'sale_price' => 4.00],
                            ['sku' => 'SUCO-TANG-UVA-18G', 'size' => '18g (uva)', 'sale_price' => 4.00],
                            ['sku' => 'SUCO-TANG-ABA-18G', 'size' => '18g (abacaxi)', 'sale_price' => 4.00],
                        ],
                    ],
                ],
            ],

            // ============================================================
            // ENERGÉTICO
            // ============================================================
            [
                'slug' => 'energetico',
                'name' => 'Energético',
                'products' => [
                    [
                        'name' => 'Monster Energy Original',
                        'brand' => 'Monster',
                        'variants' => [['sku' => 'ENER-MONSTER-ORIG-473ML', 'size' => '473ml lata', 'sale_price' => 13.00, 'min_stock' => 12]],
                    ],
                    [
                        'name' => 'Monster Energy Khaotic',
                        'brand' => 'Monster',
                        'variants' => [['sku' => 'ENER-MONSTER-KHAOTIC-473ML', 'size' => '473ml lata', 'sale_price' => 13.00]],
                    ],
                    [
                        'name' => 'Monster Energy Watermelon',
                        'brand' => 'Monster',
                        'variants' => [['sku' => 'ENER-MONSTER-WATER-473ML', 'size' => '473ml lata', 'sale_price' => 13.00]],
                    ],
                    [
                        'name' => 'Monster Juice',
                        'brand' => 'Monster',
                        'variants' => [['sku' => 'ENER-MONSTER-JUICE-473ML', 'size' => '473ml lata', 'sale_price' => 13.00]],
                    ],
                    [
                        'name' => 'Monster Ultra (sem açúcar)',
                        'brand' => 'Monster',
                        'variants' => [['sku' => 'ENER-MONSTER-ULTRA-473ML', 'size' => '473ml lata', 'sale_price' => 13.00]],
                    ],
                    [
                        'name' => 'Red Bull Energy Drink',
                        'brand' => 'Red Bull',
                        'variants' => [['sku' => 'ENER-REDBULL-250ML', 'size' => '250ml lata', 'sale_price' => 12.00]],
                    ],
                ],
            ],

            // ============================================================
            // ISOTÔNICO / FUNCIONAL
            // ============================================================
            [
                'slug' => 'isotonico',
                'name' => 'Isotônico',
                'products' => [
                    [
                        'name' => 'Powerade Mix de Frutas',
                        'brand' => 'Powerade',
                        'variants' => [['sku' => 'ISO-POWERADE-MIX-500ML', 'size' => '500ml', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Powerade Frutas Tropicais',
                        'brand' => 'Powerade',
                        'variants' => [['sku' => 'ISO-POWERADE-TROP-500ML', 'size' => '500ml', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Gatorade Morango com Maracujá',
                        'brand' => 'Gatorade',
                        'variants' => [['sku' => 'ISO-GATORADE-MORMAR-500ML', 'size' => '500ml', 'sale_price' => 7.50]],
                    ],
                    [
                        'name' => 'H2OH! Limão',
                        'brand' => 'H2OH!',
                        'variants' => [['sku' => 'ISO-H2OH-LIM-500ML', 'size' => '500ml', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Tônica Antarctica Bridgerton',
                        'brand' => 'Antarctica',
                        'variants' => [['sku' => 'ISO-TONICA-350ML', 'size' => '350ml lata', 'sale_price' => 6.00]],
                    ],
                ],
            ],

            // ============================================================
            // ALIMENTOS — preços lidos da tabela manuscrita do depósito
            // ============================================================
            [
                'slug' => 'alimentos',
                'name' => 'Alimentos',
                'products' => [
                    [
                        'name' => 'Arroz Branco Tipo 1',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'ALIM-ARROZ-1KG', 'size' => '1kg', 'sale_price' => 5.50, 'min_stock' => 30]],
                    ],
                    [
                        'name' => 'Feijão Carioca Tipo 1',
                        'brand' => '5 Estrelas',
                        'variants' => [['sku' => 'ALIM-FEIJAO5EST-1KG', 'size' => '1kg', 'sale_price' => 7.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Sal Refinado',
                        'brand' => 'Caravelas',
                        'variants' => [['sku' => 'ALIM-SAL-CARAVELAS-1KG', 'size' => '1kg', 'sale_price' => 3.50, 'min_stock' => 30]],
                    ],
                    [
                        'name' => 'Sal Grosso',
                        'brand' => 'Caravelas',
                        'variants' => [['sku' => 'ALIM-SALGROSSO-1KG', 'size' => '1kg', 'sale_price' => 6.00, 'min_stock' => 12]],
                    ],
                    [
                        'name' => 'Açúcar Refinado',
                        'brand' => 'Caravelas',
                        'variants' => [['sku' => 'ALIM-ACUCAR-CARAVELAS-1KG', 'size' => '1kg', 'sale_price' => 6.00, 'min_stock' => 30]],
                    ],
                    [
                        'name' => 'Café Torrado e Moído',
                        'brand' => '3 Corações',
                        'variants' => [['sku' => 'ALIM-CAFE-3COR-500G', 'size' => '500g', 'sale_price' => 21.00, 'min_stock' => 12]],
                    ],
                    [
                        'name' => 'Café Solúvel Nescafé',
                        'brand' => 'Nescafé',
                        'variants' => [['sku' => 'ALIM-NESCAFE-100G', 'size' => '100g', 'sale_price' => 7.50]],
                    ],
                    [
                        'name' => 'Farinha de Trigo',
                        'brand' => 'Tia Ofélia',
                        'variants' => [['sku' => 'ALIM-TRIGO-OFELIA-1KG', 'size' => '1kg', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Amido de Milho (Maizena)',
                        'brand' => 'Maizena',
                        'variants' => [['sku' => 'ALIM-MAIZENA-500G', 'size' => '500g', 'sale_price' => 7.50]],
                    ],
                    [
                        'name' => 'Fermento em Pó Royal',
                        'brand' => 'Royal',
                        'variants' => [['sku' => 'ALIM-FERMENTO-ROYAL-100G', 'size' => '100g', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Vinagre',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'ALIM-VINAGRE-750ML', 'size' => '750ml', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Óleo de Soja',
                        'brand' => 'Liza',
                        'variants' => [['sku' => 'ALIM-OLEO-LIZA-900ML', 'size' => '900ml', 'sale_price' => 8.50, 'min_stock' => 24]],
                    ],
                    [
                        'name' => 'Caldo Concentrado Maggi',
                        'brand' => 'Maggi',
                        'description' => 'Tabletes (galinha, carne, etc.)',
                        'variants' => [['sku' => 'ALIM-CALDO-MAGGI-57G', 'size' => '57g (6 tabletes)', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Tempero Verde Hellmann\'s',
                        'brand' => 'Hellmann\'s',
                        'variants' => [['sku' => 'ALIM-TEMPERO-HELL-180G', 'size' => '180g', 'sale_price' => 4.50]],
                    ],
                    [
                        'name' => 'Tempero Sazón',
                        'brand' => 'Ajinomoto',
                        'variants' => [
                            ['sku' => 'ALIM-SAZON-VERMELHA-60G', 'size' => '60g (vermelha — carnes)', 'sale_price' => 4.00],
                            ['sku' => 'ALIM-SAZON-VERDE-60G', 'size' => '60g (verde — alho e salsa)', 'sale_price' => 4.00],
                            ['sku' => 'ALIM-SAZON-AMARELA-60G', 'size' => '60g (amarela — galinha)', 'sale_price' => 4.00],
                        ],
                    ],
                    [
                        'name' => 'Farinha de Mandioca',
                        'brand' => 'KiSabor',
                        'variants' => [['sku' => 'ALIM-MANDIOCA-KISABOR-500G', 'size' => '500g', 'sale_price' => 5.00]],
                    ],
                    [
                        'name' => 'Farinha de Rosca',
                        'brand' => 'KiSabor',
                        'variants' => [['sku' => 'ALIM-ROSCA-KISABOR-250G', 'size' => '250g', 'sale_price' => 4.50]],
                    ],
                    [
                        'name' => 'Fubá Mimoso',
                        'brand' => 'Mimoso',
                        'variants' => [['sku' => 'ALIM-FUBA-MIMOSO-500G', 'size' => '500g', 'sale_price' => 4.00]],
                    ],
                    [
                        'name' => 'Cuscuz de Milho',
                        'brand' => 'Kinino',
                        'variants' => [['sku' => 'ALIM-CUSCUZ-KININO-500G', 'size' => '500g', 'sale_price' => 4.00]],
                    ],
                    [
                        // Pipoca PRONTA (já estourada e temperada)
                        'name' => 'Pipoca Premium (pronta)',
                        'brand' => 'KiSabor',
                        'variants' => [['sku' => 'ALIM-PIPOCAPRONTA-KISABOR-400G', 'size' => '400g', 'sale_price' => 5.00]],
                    ],
                    [
                        // NOVO: Milho de pipoca (em grão, pacote para estourar)
                        'name' => 'Milho de Pipoca',
                        'brand' => 'Genérica',
                        'description' => 'Milho seco para estourar pipoca',
                        'variants' => [['sku' => 'ALIM-MILHOPIPOCA-500G', 'size' => '500g', 'sale_price' => 5.50, 'min_stock' => 12]],
                    ],
                    [
                        // NOVO: Milho verde em espiga
                        'name' => 'Milho Verde em Espiga',
                        'brand' => 'Genérica',
                        'description' => 'Espiga de milho verde (congelado/refrigerado)',
                        'variants' => [['sku' => 'ALIM-MILHOESPIGA-UN', 'size' => 'unidade', 'sale_price' => 6.00, 'min_stock' => 24]],
                    ],
                ],
            ],

            // ============================================================
            // MASSAS
            // ============================================================
            [
                'slug' => 'massas',
                'name' => 'Massas',
                'products' => [
                    [
                        'name' => 'Macarrão Instantâneo Sabor Carne',
                        'brand' => 'Apti',
                        'variants' => [['sku' => 'MASSA-APTI-CARNE-85G', 'size' => '85g', 'sale_price' => 2.50, 'min_stock' => 60]],
                    ],
                    [
                        'name' => 'Macarrão Instantâneo Sabor Galinha',
                        'brand' => 'Apti',
                        'variants' => [['sku' => 'MASSA-APTI-GALINHA-85G', 'size' => '85g', 'sale_price' => 2.50, 'min_stock' => 60]],
                    ],
                    [
                        'name' => 'Macarrão Parafuso com Ovos',
                        'brand' => 'Petybon',
                        'variants' => [['sku' => 'MASSA-PETYBON-PARAFUSO-500G', 'size' => '500g', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Macarrão Espaguete de Sêmola com Ovos',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'MASSA-SEMOLA-ESPAG-500G', 'size' => '500g', 'sale_price' => 6.00]],
                    ],
                ],
            ],

            // ============================================================
            // CONSERVAS / ENLATADOS
            // ============================================================
            [
                'slug' => 'conservas',
                'name' => 'Conservas',
                'products' => [
                    [
                        'name' => 'Milho em Conserva',
                        'brand' => 'Sofruta',
                        'variants' => [['sku' => 'CONS-MILHO-SOFRUTA-170G', 'size' => '170g (drenado)', 'sale_price' => 5.00, 'min_stock' => 24]],
                    ],
                    [
                        'name' => 'Ervilha em Conserva',
                        'brand' => 'Etti',
                        'variants' => [['sku' => 'CONS-ERVILHA-ETTI-170G', 'size' => '170g (drenado)', 'sale_price' => 5.00]],
                    ],
                    [
                        'name' => 'Salsicha em Lata',
                        'brand' => 'Bordon',
                        'variants' => [['sku' => 'CONS-SALSICHA-BORDON-180G', 'size' => '180g (drenado)', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Sardinha em Óleo',
                        'brand' => 'Pescador',
                        'description' => 'Peso líquido 125g | drenado 75g — rica em ômega 3',
                        'variants' => [['sku' => 'CONS-SARDINHA-PESCADOR-125G', 'size' => '125g', 'sale_price' => 7.00, 'min_stock' => 24]],
                    ],
                    [
                        'name' => 'Pepino em Conserva',
                        'brand' => 'Campo Belo',
                        'variants' => [['sku' => 'CONS-PEPINO-CAMPOBELO-300G', 'size' => '300g', 'sale_price' => 5.00]],
                    ],
                    [
                        'name' => 'Azeitona Verde sem Caroço',
                        'brand' => 'Campo Belo',
                        'variants' => [['sku' => 'CONS-AZEITONA-CAMPOBELO-150G', 'size' => '150g', 'sale_price' => 8.50]],
                    ],
                    [
                        'name' => 'Palmito Pupunha Picado',
                        'brand' => 'Famiglia',
                        'variants' => [['sku' => 'CONS-PALMITO-FAMIGLIA-500G', 'size' => '500g', 'sale_price' => 15.00]],
                    ],
                    [
                        'name' => 'Molho de Tomate Tradicional',
                        'brand' => 'Tomadoro',
                        'variants' => [['sku' => 'CONS-MOLHO-TOMADORO-300G', 'size' => '300g sachê', 'sale_price' => 3.50, 'min_stock' => 36]],
                    ],
                    [
                        'name' => 'Maionese Hellmann\'s',
                        'brand' => 'Hellmann\'s',
                        'description' => 'A verdadeira maionese (Unilever)',
                        'variants' => [
                            ['sku' => 'CONS-MAIONESE-HELL-250G', 'size' => '250g', 'sale_price' => 4.00],
                            ['sku' => 'CONS-MAIONESE-HELL-500G', 'size' => '500g', 'sale_price' => 8.50],
                        ],
                    ],
                    [
                        'name' => 'Leite de Coco',
                        'brand' => 'Sococo',
                        'variants' => [['sku' => 'CONS-LEITECOCO-SOCOCO-200ML', 'size' => '200ml', 'sale_price' => 6.00]],
                    ],
                ],
            ],

            // ============================================================
            // LATICÍNIOS / UHT — preço de creme de leite corrigido (4,00 → 6,00)
            // ============================================================
            [
                'slug' => 'laticinios',
                'name' => 'Laticínios',
                'products' => [
                    [
                        'name' => 'Leite UHT Integral',
                        'brand' => 'Italac',
                        'description' => 'Teor de gordura 3%',
                        'variants' => [['sku' => 'LAT-LEITE-ITALAC-1L', 'size' => '1L', 'sale_price' => 6.00, 'min_stock' => 36]],
                    ],
                    [
                        'name' => 'Creme de Leite UHT',
                        'brand' => 'Casaberta',
                        // Tabela manuscrita confirmou R$ 6,00 (corrigido de 4,00)
                        'variants' => [['sku' => 'LAT-CREMELEITE-CASA-200G', 'size' => '200g', 'sale_price' => 6.00, 'min_stock' => 24]],
                    ],
                    [
                        'name' => 'Leite Condensado',
                        'brand' => 'Milk+',
                        'variants' => [['sku' => 'LAT-CONDENSADO-MILK-395G', 'size' => '395g', 'sale_price' => 10.00]],
                    ],
                    [
                        'name' => 'Queijo Parmesão Ralado',
                        'brand' => 'Ipanema',
                        'description' => '40 anos — nova receita',
                        'variants' => [['sku' => 'LAT-PARMESAO-IPANEMA-40G', 'size' => '40g', 'sale_price' => 4.50, 'min_stock' => 24]],
                    ],
                ],
            ],

            // ============================================================
            // FRIOS / EMBUTIDOS — Salame: pedaço R$ 4,00 e peça R$ 6,00 (tabela)
            // ============================================================
            [
                'slug' => 'frios',
                'name' => 'Frios',
                'products' => [
                    [
                        'name' => 'Salame Italiano',
                        'brand' => 'Genérica',
                        'description' => 'Refrigerado — vendido fatiado por peso ou em peça',
                        'variants' => [
                            // Tabela manuscrita: "Salame pedaço" 4,00 e "Salame peça" 6,00
                            ['sku' => 'FRIO-SALAME-PEDACO-100G', 'size' => '100g (pedaço fatiado)', 'sale_price' => 4.00, 'min_stock' => 12],
                            ['sku' => 'FRIO-SALAME-PECA-200G', 'size' => '200g (peça)', 'sale_price' => 6.00, 'min_stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Salsicha Resfriada',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'FRIO-SALSICHA-500G', 'size' => '500g (pacote)', 'sale_price' => 12.00, 'min_stock' => 12]],
                    ],
                ],
            ],

            // ============================================================
            // SNACKS / SALGADINHOS — Torcida R$ 3,50 (todas variações)
            // ============================================================
            [
                'slug' => 'snacks',
                'name' => 'Snacks',
                'products' => [
                    [
                        'name' => 'Salgadinho Torcida Cebola',
                        'brand' => 'Torcida',
                        'variants' => [['sku' => 'SNACK-TORCIDA-CEB-60G', 'size' => '60g', 'sale_price' => 3.50, 'min_stock' => 60]],
                    ],
                    [
                        'name' => 'Salgadinho Torcida Pimenta Mexicana',
                        'brand' => 'Torcida',
                        'variants' => [['sku' => 'SNACK-TORCIDA-PIM-60G', 'size' => '60g', 'sale_price' => 3.50, 'min_stock' => 60]],
                    ],
                    [
                        'name' => 'Salgadinho Torcida Vinagrete',
                        'brand' => 'Torcida',
                        'variants' => [['sku' => 'SNACK-TORCIDA-VIN-60G', 'size' => '60g', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Salgadinho Torcida Pão de Alho',
                        'brand' => 'Torcida',
                        'variants' => [['sku' => 'SNACK-TORCIDA-PAO-60G', 'size' => '60g', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Salgadinho Torcida Costela com Limão',
                        'brand' => 'Torcida',
                        'variants' => [['sku' => 'SNACK-TORCIDA-COST-60G', 'size' => '60g', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Batata Frita à Granel',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'SNACK-BATATA-100G', 'size' => '100g', 'sale_price' => 5.00]],
                    ],
                    [
                        'name' => 'Amendoim Torrado à Granel',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'SNACK-AMENDOIM-100G', 'size' => '100g', 'sale_price' => 4.00]],
                    ],
                ],
            ],

            // ============================================================
            // DOCES
            // ============================================================
            [
                'slug' => 'doces',
                'name' => 'Doces',
                'products' => [
                    [
                        'name' => 'Chiclete Bubbaloo Morango',
                        'brand' => 'Bubbaloo',
                        'variants' => [
                            ['sku' => 'DOCE-BUBBALOO-MOR-UN', 'size' => 'unidade', 'sale_price' => 1.00],
                            ['sku' => 'DOCE-BUBBALOO-MOR-CX', 'size' => 'caixa 60un', 'sale_price' => 50.00],
                        ],
                    ],
                    [
                        'name' => 'Bala Halls Morango',
                        'brand' => 'Halls',
                        'variants' => [['sku' => 'DOCE-HALLS-MOR-UN', 'size' => 'unidade', 'sale_price' => 2.50]],
                    ],
                    [
                        'name' => 'Bala Halls Extra Forte',
                        'brand' => 'Halls',
                        'variants' => [['sku' => 'DOCE-HALLS-EXT-UN', 'size' => 'unidade', 'sale_price' => 2.50]],
                    ],
                    [
                        'name' => 'Pirulito Florestal Cereja',
                        'brand' => 'Florestal',
                        'variants' => [['sku' => 'DOCE-FLORESTAL-CER-UN', 'size' => 'unidade', 'sale_price' => 0.50]],
                    ],
                    [
                        'name' => 'Paçoquinha',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'DOCE-PACOQUINHA-UN', 'size' => 'unidade', 'sale_price' => 1.00]],
                    ],
                    [
                        'name' => 'Palha Italiana',
                        'brand' => 'Doces Ouro de Minas',
                        'variants' => [['sku' => 'DOCE-OUROMINAS-PALHA-UN', 'size' => 'unidade 50g', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Pingo Bel (doce de leite)',
                        'brand' => 'Doces Ouro de Minas',
                        'variants' => [['sku' => 'DOCE-OUROMINAS-PINGOBEL-UN', 'size' => 'unidade 50g', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Beijo (doce de leite com coco)',
                        'brand' => 'Doces Ouro de Minas',
                        'variants' => [['sku' => 'DOCE-OUROMINAS-BEIJO-UN', 'size' => 'unidade 50g', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Cocada Caseira',
                        'brand' => 'Tachinho da Rosa',
                        'variants' => [['sku' => 'DOCE-TACHINHO-COCADA-UN', 'size' => 'unidade ~50g', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Doce de Batata AB',
                        'brand' => 'Iranel Doces',
                        'variants' => [['sku' => 'DOCE-IRANEL-BATATA-UN', 'size' => 'unidade 55g', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Quebra Queixo',
                        'brand' => 'Iranel Doces',
                        'variants' => [['sku' => 'DOCE-IRANEL-QUEBRA-UN', 'size' => 'unidade 50g', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Bananinha Caipira',
                        'brand' => 'Delícia Caseira',
                        'variants' => [['sku' => 'DOCE-DELICIA-BANANINHA-35G', 'size' => '35g', 'sale_price' => 2.50]],
                    ],
                    [
                        'name' => 'Paçoca',
                        'brand' => 'Doces Aurora',
                        'variants' => [['sku' => 'DOCE-AURORA-PACOCA-75G', 'size' => '75g', 'sale_price' => 4.00]],
                    ],
                    [
                        'name' => 'Pão de Mel',
                        'brand' => 'Doces Brumeli',
                        'variants' => [['sku' => 'DOCE-BRUMELI-PAOMEL-UN', 'size' => 'unidade', 'sale_price' => 5.00]],
                    ],
                ],
            ],

            // ============================================================
            // LIMPEZA — preços corrigidos pela tabela manuscrita
            // ============================================================
            [
                'slug' => 'limpeza',
                'name' => 'Limpeza',
                'products' => [
                    [
                        'name' => 'Detergente Líquido Lava-Louças',
                        'brand' => 'Ypê',
                        'variants' => [['sku' => 'LIMP-DETERG-YPE-500ML', 'size' => '500ml', 'sale_price' => 3.00, 'min_stock' => 24]],
                    ],
                    [
                        'name' => 'Sabão em Pedra',
                        'brand' => 'Ypê',
                        'variants' => [['sku' => 'LIMP-SABAOPED-YPE', 'size' => 'pacote 5un', 'sale_price' => 11.00]],
                    ],
                    [
                        'name' => 'Sabão em Pó Tira Manchas',
                        'brand' => 'Ypê',
                        'variants' => [['sku' => 'LIMP-POYPE-380G', 'size' => '380g', 'sale_price' => 6.50]],
                    ],
                    [
                        'name' => 'Amaciante Concentrado Ypê',
                        'brand' => 'Ypê',
                        'description' => 'Rende 2L (25 lavagens) - novo perfume',
                        'variants' => [['sku' => 'LIMP-AMACIANTE-YPE-500ML', 'size' => '500ml', 'sale_price' => 12.00]],
                    ],
                    [
                        'name' => 'Esponja de Aço Bombril',
                        'brand' => 'Bombril',
                        'variants' => [['sku' => 'LIMP-BOMBRIL-8UN', 'size' => '8 unidades', 'sale_price' => 3.50]],
                    ],
                    [
                        'name' => 'Bucha para Louça',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'LIMP-BUCHA-UN', 'size' => 'unidade', 'sale_price' => 1.50]],
                    ],
                    [
                        'name' => 'Folha de Alumínio',
                        'brand' => 'Wyda',
                        'variants' => [['sku' => 'LIMP-ALU-WYDA-4M', 'size' => '4m × 30cm', 'sale_price' => 4.50]],
                    ],
                    [
                        'name' => 'Água Sanitária',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'LIMP-SANIT-1L', 'size' => '1L', 'sale_price' => 5.00]],
                    ],
                ],
            ],

            // ============================================================
            // HIGIENE PESSOAL E DESCARTÁVEIS
            // ============================================================
            [
                'slug' => 'higiene',
                'name' => 'Higiene Pessoal',
                'products' => [
                    [
                        'name' => 'Creme Dental Anticáries',
                        'brand' => 'Oral-B',
                        'variants' => [['sku' => 'HIG-ORALB-90G', 'size' => '90g', 'sale_price' => 3.50, 'min_stock' => 12]],
                    ],
                    [
                        'name' => 'Escova de Dente Classic Clean',
                        'brand' => 'Colgate',
                        'description' => 'Cerdas macias - remove placa bacteriana',
                        'variants' => [
                            ['sku' => 'HIG-ESC-COLGATE-MACIA', 'size' => 'macia', 'sale_price' => 4.50, 'min_stock' => 12],
                            ['sku' => 'HIG-ESC-COLGATE-PREM', 'size' => 'premium', 'sale_price' => 7.00],
                        ],
                    ],
                    [
                        'name' => 'Papel Higiênico Folha Dupla',
                        'brand' => 'Fofinho',
                        'variants' => [
                            ['sku' => 'HIG-FOFINHO-4R', 'size' => '4 rolos', 'sale_price' => 5.50],
                            ['sku' => 'HIG-FOFINHO-30R', 'size' => '30 rolos', 'sale_price' => 35.00, 'min_stock' => 6],
                        ],
                    ],
                    [
                        'name' => 'Filtro de Papel para Café',
                        'brand' => '3 Corações',
                        'variants' => [
                            ['sku' => 'HIG-FILTRO-3COR-N2', 'size' => 'nº 2 (30un)', 'sale_price' => 6.00],
                            ['sku' => 'HIG-FILTRO-3COR-N3', 'size' => 'nº 3 (30un)', 'sale_price' => 6.50],
                        ],
                    ],
                    [
                        'name' => 'Papel Toalha',
                        'brand' => 'Sorelle',
                        'variants' => [['sku' => 'HIG-TOALHA-SORELLE', 'size' => '2 rolos', 'sale_price' => 6.00]],
                    ],
                    [
                        'name' => 'Guardanapo de Papel',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'HIG-GUARD-50UN', 'size' => '50 unidades', 'sale_price' => 3.00]],
                    ],
                    [
                        'name' => 'Palito de Dente',
                        'brand' => 'Gina',
                        'variants' => [['sku' => 'HIG-PALITO-200UN', 'size' => 'caixa 200un', 'sale_price' => 1.50]],
                    ],
                    [
                        'name' => 'Prato Descartável',
                        'brand' => 'Genérica',
                        'variants' => [['sku' => 'HIG-PRATO-DESC-100UN', 'size' => '100 unidades', 'sale_price' => 15.00]],
                    ],
                ],
            ],

            // ============================================================
            // CIGARROS — todos a R$ 10,00 conforme orientação
            // ============================================================
            [
                'slug' => 'cigarros',
                'name' => 'Cigarros',
                'products' => [
                    [
                        'name' => 'Marlboro Vermelho',
                        'brand' => 'Marlboro',
                        'variants' => [['sku' => 'CIG-MARLBORO-RED', 'size' => 'maço 20un', 'sale_price' => 10.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Marlboro Gold',
                        'brand' => 'Marlboro',
                        'variants' => [['sku' => 'CIG-MARLBORO-GOLD', 'size' => 'maço 20un', 'sale_price' => 10.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Derby',
                        'brand' => 'Derby',
                        'variants' => [['sku' => 'CIG-DERBY', 'size' => 'maço 20un', 'sale_price' => 10.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Lucky Strike',
                        'brand' => 'Lucky Strike',
                        'variants' => [['sku' => 'CIG-LUCKY', 'size' => 'maço 20un', 'sale_price' => 10.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Eight',
                        'brand' => 'Eight',
                        'variants' => [['sku' => 'CIG-EIGHT', 'size' => 'maço 20un', 'sale_price' => 10.00]],
                    ],
                    [
                        'name' => 'Hilton',
                        'brand' => 'Hilton',
                        'variants' => [['sku' => 'CIG-HILTON', 'size' => 'maço 20un', 'sale_price' => 10.00]],
                    ],
                    [
                        'name' => 'Free',
                        'brand' => 'Free',
                        'variants' => [['sku' => 'CIG-FREE', 'size' => 'maço 20un', 'sale_price' => 10.00]],
                    ],
                    [
                        'name' => 'Carlton',
                        'brand' => 'Carlton',
                        'variants' => [['sku' => 'CIG-CARLTON', 'size' => 'maço 20un', 'sale_price' => 10.00]],
                    ],
                ],
            ],

            // ============================================================
            // TABACO ACESSÓRIOS (sedas, palhas)
            // ============================================================
            [
                'slug' => 'tabaco-acessorios',
                'name' => 'Tabaco - Acessórios',
                'products' => [
                    [
                        'name' => 'Seda Hemp Organic Rolling Papers',
                        'brand' => 'Hemp',
                        'variants' => [['sku' => 'TAB-SEDA-HEMP-33', 'size' => '33 folhas', 'sale_price' => 2.50]],
                    ],
                ],
            ],

            // ============================================================
            // CARVÃO
            // ============================================================
            [
                'slug' => 'carvao',
                'name' => 'Carvão',
                'products' => [
                    [
                        'name' => 'Carvão Vegetal para Churrasco',
                        'brand' => 'Genérica',
                        'variants' => [
                            ['sku' => 'CARV-3KG', 'size' => '2kg', 'sale_price' => 15.00, 'min_stock' => 10],
                            ['sku' => 'CARV-5KG', 'size' => '4kg', 'sale_price' => 30.00, 'min_stock' => 10],
                        ],
                    ],
                ],
            ],

            // ============================================================
            // OUTROS / ACESSÓRIOS
            // ============================================================
            [
                'slug' => 'outros',
                'name' => 'Outros',
                'products' => [
                    [
                        'name' => 'Isqueiro',
                        'brand' => 'BiC',
                        'variants' => [['sku' => 'OUT-ISQUEIRO-BIC', 'size' => 'unidade', 'sale_price' => 9.00, 'min_stock' => 20]],
                    ],
                    [
                        'name' => 'Fósforo Longo Fiat Lux',
                        'brand' => 'Fiat Lux',
                        'description' => 'Caixa com 300 fósforos longos para casa',
                        'variants' => [['sku' => 'OUT-FOSFORO-FIAT-300', 'size' => '300 unidades', 'sale_price' => 1.00, 'min_stock' => 12]],
                    ],
                    [
                        'name' => 'Pilha Alcalina AA Panasonic',
                        'brand' => 'Panasonic',
                        'description' => 'Power Alkaline - dura até 10x mais - cartela 2 unidades',
                        'variants' => [['sku' => 'OUT-PILHA-PANA-AA-2', 'size' => 'cartela 2un', 'sale_price' => 12.00]],
                    ],
                    [
                        'name' => 'Lâmpada LED Ourolux SuperLed',
                        'brand' => 'Ourolux',
                        'description' => 'Multi-tensão, luz branca fria, alta potência',
                        'variants' => [
                            ['sku' => 'OUT-LAMP-OURO-15W', 'size' => '15W', 'sale_price' => 9.50],
                            ['sku' => 'OUT-LAMP-OURO-40W', 'size' => '40W', 'sale_price' => 25.00],
                        ],
                    ],
                    [
                        'name' => 'Bomba Automática para Galão',
                        'brand' => 'M&CH',
                        'description' => 'Acessório para galão de água com USB',
                        'variants' => [['sku' => 'OUT-BOMBA-MCH', 'size' => 'unidade', 'sale_price' => 35.00, 'min_stock' => 5]],
                    ],
                ],
            ],
        ];
    }
}