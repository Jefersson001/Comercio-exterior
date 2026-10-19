<?php

namespace Database\Factories;

use App\Models\MscShipment;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<MscShipment>
 */
class MscShipmentFactory extends EloquentFactory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'etd' => '2026-10-01',
            'eta' => '2026-11-01',
            'bl_master' => fake()->unique()->bothify('MSC-########'),
            'factory_id' => Factory::factory(),
            'product_id' => Product::factory(),
            'container_qty' => 2,
            'free_time' => 21,
            'vessel_id' => Vessel::factory(),
            'released' => null,
        ];
    }
}
