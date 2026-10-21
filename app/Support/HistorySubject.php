<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;

class HistorySubject
{
    /** @return array<string, class-string<Model>> */
    public static function models(): array
    {
        return ['msc' => MscShipment::class, 'product' => Product::class, 'category' => Category::class, 'factory' => Factory::class, 'vessel' => Vessel::class, 'user' => User::class];
    }

    public static function type(Model $model): ?string
    {
        return array_search($model::class, self::models(), true) ?: null;
    }

    public static function gate(string $type, bool $foreignTrade = false): string
    {
        return match ($type) {
            'msc' => $foreignTrade ? 'access-foreign-trade' : 'access-international-trade',
            'product' => 'manage-products', 'category' => 'manage-categories',
            'factory' => 'manage-factories', 'vessel' => 'manage-vessels', 'user' => 'manage-users',
            default => 'manage-users',
        };
    }

    /**
     * Explicit allowlists: security credentials must never enter the history.
     *
     * @return array<string, string>
     */
    public static function fields(string $type): array
    {
        return match ($type) {
            'msc' => [...MscFields::labels(), 'status' => 'Status del embarque', 'products' => 'Productos del embarque', 'containers' => 'Contenedores', 'colors' => 'Variantes del embarque'],
            'product' => ['name' => 'Nombre', 'category_id' => 'Categoría', 'units_per_container' => 'Unidades por contenedor', 'colors' => 'Variantes por color'],
            'category' => ['name' => 'Nombre', 'allows_multiple_products' => 'Es para múltiples productos'],
            'factory', 'vessel' => ['name' => 'Nombre'],
            'user' => ['name' => 'Nombre', 'last_name' => 'Apellido', 'email' => 'Correo electrónico', 'role' => 'Rol', 'is_active' => 'Usuario activo', 'access_international_trade' => 'Acceso a Comercio Internacional', 'access_foreign_trade' => 'Acceso a Comercio Exterior', 'access_configuration' => 'Acceso a Configuración'],
            default => [],
        };
    }

    /** @return list<string> */
    public static function internationalFields(): array
    {
        return ['etd', 'eta', 'bl_master', 'bl_house', 'factory_id', 'product_id', 'container_qty', 'units_qty', 'free_time', 'vessel_id', 'released', 'status', 'products'];
    }
}
