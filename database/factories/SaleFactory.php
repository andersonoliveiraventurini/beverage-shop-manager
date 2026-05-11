<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'address_id' => null,
            'user_id' => null,
            'type' => Sale::TYPE_COUNTER,
            'payment_method' => Sale::PAYMENT_CASH,
            'status' => Sale::STATUS_OPEN,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'building_fee' => 0,
            'card_fee' => 0,
            'discount' => 0,
            'total' => 0,
            'contains_water' => false,
        ];
    }

    public function delivery(): static
    {
        return $this->state([
            'type' => Sale::TYPE_DELIVERY,
            'payment_method' => Sale::PAYMENT_PIX,
            'delivery_fee' => 2.00,
        ]);
    }
}
