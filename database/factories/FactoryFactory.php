<?php

namespace Database\Factories;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Factory>
 */
class FactoryFactory extends EloquentFactory
{
    protected $model = Factory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company()];
    }
}
