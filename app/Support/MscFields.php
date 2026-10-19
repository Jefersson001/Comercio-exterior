<?php

namespace App\Support;

use App\Enums\MscShipmentStatus;
use App\Models\MscShipment;
use Illuminate\Validation\Rule;

class MscFields
{
    public const array HEADERS = [
        'no' => 'No.', 'etd' => 'ETD', 'eta' => 'ETA', 'bl_master' => 'BL Master',
        'bl_house' => 'BL House', 'factory' => 'Factory', 'model' => 'Model', 'container_qty' => 'Cant contenedores',
        'units_qty' => 'Cant Unidades', 'free_time' => 'Free time', 'vessel' => 'Buque', 'released' => 'RELEASED',
        'status' => 'Status',
    ];

    /** @return array<string, array<mixed>> */
    public static function rules(?int $id = null, bool $foreignTrade = false, string $blHouse = ''): array
    {
        return [
            'etd' => ['required', 'date_format:Y-m-d'],
            'eta' => ['required', 'date_format:Y-m-d', 'after_or_equal:etd'],
            'bl_master' => ['required', 'string', 'max:255', Rule::unique(MscShipment::class, 'bl_master')->where('bl_house', $blHouse)->ignore($id)],
            'bl_house' => ['nullable', 'string', 'max:255'],
            'factory_id' => ['nullable', 'integer', 'exists:factories,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'container_qty' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'units_qty' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'free_time' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'vessel_id' => ['nullable', 'integer', 'exists:vessels,id'],
            'released' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(MscShipmentStatus::class)],
            ...($foreignTrade ? ['category_id' => ['nullable', 'integer', 'exists:categories,id']] : []),
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return ['product_id' => 'Model', 'factory_id' => 'Fábrica', 'vessel_id' => 'Buque', 'category_id' => 'Categoría', 'bl_master' => 'BL Master', 'bl_house' => 'BL House', ...self::HEADERS];
    }

    /** @return array<string, string> */
    public static function headers(bool $foreignTrade = false): array
    {
        return [...self::HEADERS, ...($foreignTrade ? ['category' => 'Categoría'] : [])];
    }
}
