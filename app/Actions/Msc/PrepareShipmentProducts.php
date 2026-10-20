<?php

namespace App\Actions\Msc;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrepareShipmentProducts
{
    /**
     * @param  array<int, array{product_id: int|string|null, container_qty: int|string|null, units_qty: int|string|null}>  $lines
     * @return array{lines: list<array{product_id: int, container_qty: int, units_qty: ?int}>, container_qty: int, units_qty: ?int}
     */
    public function handle(?int $categoryId, array $lines): array
    {
        $category = Category::find($categoryId);
        $data = Validator::make(['category_id' => $categoryId, 'productLines' => $lines], [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'productLines' => ['required', 'array', 'min:1', 'max:'.($category?->allows_multiple_products ? 100 : 1)],
            'productLines.*' => ['array:product_id,container_qty,units_qty'],
            'productLines.*.product_id' => ['required', 'integer', 'distinct', Rule::exists(Product::class, 'id')->where('category_id', $categoryId)],
            'productLines.*.container_qty' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'productLines.*.units_qty' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'productLines.max' => 'Esta categoría permite como máximo :max líneas de productos. Retira las líneas adicionales antes de guardar.',
            'productLines.*.product_id.exists' => 'El producto debe pertenecer a la categoría seleccionada.',
            'productLines.*.product_id.distinct' => 'No repitas el mismo producto en el embarque.',
        ], ['category_id' => 'categoría', 'productLines' => 'productos', 'productLines.*.container_qty' => 'cantidad de contenedores', 'productLines.*.units_qty' => 'cantidad de unidades'])->validate();

        $products = Product::whereIn('id', array_column($data['productLines'], 'product_id'))->get()->keyBy('id');
        $result = [];
        $containers = 0;
        $units = 0;
        $unknownUnits = false;
        foreach ($data['productLines'] as $index => $line) {
            $product = $products->get($line['product_id']);
            $count = (int) $line['container_qty'];
            $actual = in_array($line['units_qty'] ?? null, ['', null], true) ? null : (int) $line['units_qty'];
            $capacity = $product->units_per_container === null ? null : $product->units_per_container * $count;
            if ($capacity !== null && ($capacity > 2147483647 || ($actual !== null && $actual > $capacity))) {
                throw ValidationException::withMessages(["productLines.$index.units_qty" => 'La cantidad de unidades supera la capacidad de los contenedores o el máximo permitido.']);
            }
            $containers += $count;
            $unknownUnits = $unknownUnits || ($actual === null && $capacity === null);
            $units += $actual ?? $capacity ?? 0;
            $result[] = ['product_id' => (int) $line['product_id'], 'container_qty' => $count, 'units_qty' => $actual];
        }
        if ($containers > 2147483647 || $units > 2147483647) {
            throw ValidationException::withMessages(['productLines' => 'Los totales superan el máximo permitido.']);
        }

        return ['lines' => $result, 'container_qty' => $containers, 'units_qty' => $unknownUnits ? null : $units];
    }
}
