<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'document' => $this->faker->numerify('###.###.###-##'),
            'notes' => null,
            'in_delivery_area' => true,
            'distance_km' => $this->faker->randomFloat(2, 0, 5),
            'delivery_fee' => 2.00,
            'building_fee' => 0,
            'has_manual_fee_override' => false,
        ];
    }

    public function building(): static
    {
        return $this->state(['building_fee' => 1.00]);
    }

    public function outOfArea(): static
    {
        return $this->state(['in_delivery_area' => false, 'distance_km' => 6.5]);
    }
}
