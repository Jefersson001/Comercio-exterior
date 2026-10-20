<?php

namespace App\Support;

use App\Enums\MscShipmentStatus;
use App\Enums\MscBillOfLadingStatus;
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
            ...($foreignTrade ? ['status_bl' => ['nullable', Rule::enum(MscBillOfLadingStatus::class)]] : []),
            ...($foreignTrade ? [
                'exp_number' => ['nullable', 'string', 'max:255'],
                'dua_intr' => ['nullable', 'string', 'max:255'],
                'seabridge' => ['nullable', 'string', 'max:255'],
                'sencamer' => ['nullable', 'string', 'max:255'],
                'comex' => ['nullable', 'string', 'max:255'],
                'recognition_date' => ['nullable', 'date_format:Y-m-d'],
                'receipt_date' => ['nullable', 'date_format:Y-m-d'],
                'shipment_year' => ['nullable', 'integer', 'digits:4', 'min:1000', 'max:9999'],
                'etd_vnzl' => ['nullable', 'date_format:Y-m-d'],
                'eta_vnzl' => ['nullable', 'date_format:Y-m-d'],
                'port' => ['nullable', 'string', 'max:255'],
                'trasegado' => ['nullable', 'string', 'max:255'],
                'in_bond_date' => ['nullable', 'date_format:Y-m-d'],
                'free_days' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                'delay_date' => ['nullable', 'date_format:Y-m-d'],
                'legal_abandonment_date' => ['nullable', 'date_format:Y-m-d'],
                'registration_date' => ['nullable', 'date_format:Y-m-d'],
                'phase_two_recognition_date' => ['nullable', 'date_format:Y-m-d'],
                'validation_date' => ['nullable', 'date_format:Y-m-d'],
                'plant_receipt_date' => ['nullable', 'date_format:Y-m-d'],
                'location' => ['nullable', 'string', 'max:255'],
                'return_date' => ['nullable', 'date_format:Y-m-d'],
            ] : []),
            ...($foreignTrade ? ['category_id' => ['nullable', 'integer', 'exists:categories,id']] : []),
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'product_id' => 'Model',
            'factory_id' => 'Fábrica',
            'vessel_id' => 'Buque',
            'category_id' => 'Categoría',
            'status_bl' => 'Status BL',
            'bl_master' => 'BL Master',
            'bl_house' => 'BL House',
            'exp_number' => 'N°EXP.',
            'dua_intr' => 'DUA/INTR',
            'seabridge' => 'SEABRIDGE',
            'sencamer' => 'SENCAMER',
            'comex' => 'COMEX',
            'recognition_date' => 'Fecha Reconocimiento',
            'receipt_date' => 'Fecha Recepción',
            'shipment_year' => 'Año',
            'etd_vnzl' => 'ETD',
            'eta_vnzl' => 'ETA VNZL',
            'port' => 'Puerto',
            'trasegado' => 'TRASEGADO',
            'in_bond_date' => 'In bond',
            'free_days' => 'Días libres',
            'delay_date' => 'Días demora',
            'legal_abandonment_date' => 'Abandono legal',
            'registration_date' => 'Registro',
            'phase_two_recognition_date' => 'Reconocimiento',
            'validation_date' => 'Validación',
            'plant_receipt_date' => 'Recepción planta',
            'location' => 'Ubicación',
            'return_date' => 'Devolución',
            'containerLines.*.name' => 'Contenedor',
            ...self::HEADERS,
        ];
    }

    /** @return array<string, string> */
    public static function headers(bool $foreignTrade = false): array
    {
        return [...self::HEADERS, ...($foreignTrade ? ['category' => 'Categoría'] : [])];
    }
}
