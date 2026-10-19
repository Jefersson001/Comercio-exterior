<?php

namespace Database\Factories;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Vessel>
 */
class VesselFactory extends EloquentFactory
{
    protected $model = Vessel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company()];
    }
}
