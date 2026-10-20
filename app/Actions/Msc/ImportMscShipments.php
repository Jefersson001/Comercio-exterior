<?php

namespace App\Actions\Msc;

use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vessel;
use App\Support\MscFields;
use App\Support\MscReportFields;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class ImportMscShipments
{
    /** @return array{imported: int, updated: int, skipped: int} */
    public function handle(string $path, bool $dryRun = false, bool $foreignTrade = false): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        try {
            return DB::transaction(function () use ($reader, $dryRun, $foreignTrade): array {
                $catalogs = $this->catalogs();
                $shipments = MscShipment::query()->get(['id', 'bl_master', 'bl_house'])
                    ->mapWithKeys(fn (MscShipment $shipment): array => [
                        $this->billOfLadingKey($shipment->bl_master, $shipment->bl_house) => $shipment,
                    ])->all();
                $importedKeys = [];
                $updatedKeys = [];
                $skipped = 0;
                $recognizedHeaders = 0;

                foreach ([false, true] as $foreignTradeSheet) {
                    foreach ($reader->getSheetIterator() as $sheet) {
                        $header = null;
                        foreach ($sheet->getRowIterator() as $number => $row) {
                            if ($header === null) {
                                $header = $this->findHeader($row);
                                if ($header === null) {
                                    continue;
                                }

                                if ($header['foreign_trade'] !== $foreignTradeSheet) {
                                    break;
                                }
                                $recognizedHeaders++;

                                continue;
                            }

                            if ($header['foreign_trade']) {
                                $this->importForeignTradeRow(
                                    $row,
                                    $number,
                                    $header['columns'],
                                    $catalogs,
                                    $shipments,
                                    $importedKeys,
                                    $updatedKeys,
                                    $dryRun,
                                    $skipped,
                                );
                            } else {
                                $this->importInternationalRow(
                                    $row,
                                    $number,
                                    $header['columns'],
                                    $catalogs,
                                    $shipments,
                                    $importedKeys,
                                    $updatedKeys,
                                    $dryRun,
                                    $foreignTrade,
                                    $skipped,
                                );
                            }
                        }
                    }
                }

                if ($recognizedHeaders === 0) {
                    throw new InvalidArgumentException('El archivo no contiene encabezados MSC reconocidos. Usa la plantilla del sistema.');
                }

                if ($importedKeys === [] && $updatedKeys === [] && $skipped === 0) {
                    throw new InvalidArgumentException('El archivo no contiene registros MSC.');
                }

                return [
                    'imported' => count($importedKeys),
                    'updated' => count($updatedKeys),
                    'skipped' => $skipped,
                ];
            });
        } finally {
            $reader->close();
        }
    }

    /**
     * @return array{
     *     products: array<string, array{id: int, units_per_container: int|null}>,
     *     productsById: array<int, array{id: int, units_per_container: int|null}>,
     *     categories: array<string, int|null>,
     *     factories: array<string, int|null>,
     *     vessels: array<string, int|null>,
     *     variants: array<string, int>
     * }
     */
    private function catalogs(): array
    {
        $products = [];
        $productsById = [];
        foreach (Product::query()->get(['id', 'name', 'units_per_container']) as $product) {
            $key = $this->normalize($product->name);
            if (isset($products[$key])) {
                throw new InvalidArgumentException('Hay productos con nombres equivalentes. Revisa el catálogo antes de importar MSC.');
            }
            $products[$key] = ['id' => $product->id, 'units_per_container' => $product->units_per_container];
            $productsById[$product->id] = ['id' => $product->id, 'units_per_container' => $product->units_per_container];
        }

        $categories = [];
        foreach (Category::query()->get(['id', 'name']) as $category) {
            $key = $this->normalize($category->name);
            $categories[$key] = array_key_exists($key, $categories) ? null : $category->id;
        }

        $factories = [];
        foreach (Factory::query()->get(['id', 'name']) as $factory) {
            $key = $this->normalize($factory->name);
            $factories[$key] = array_key_exists($key, $factories) ? null : $factory->id;
        }

        $vessels = [];
        foreach (Vessel::query()->get(['id', 'name']) as $vessel) {
            $key = $this->normalize($vessel->name);
            $vessels[$key] = array_key_exists($key, $vessels) ? null : $vessel->id;
        }

        $variants = [];
        foreach (ProductVariant::query()->get(['id', 'product_id', 'color']) as $variant) {
            $variants[$variant->product_id."\0".$this->normalize($variant->color)] = $variant->id;
        }

        return compact('products', 'productsById', 'categories', 'factories', 'vessels', 'variants');
    }

    /**
     * @return array{foreign_trade: bool, columns: array<string, int>}|null
     */
    private function findHeader(Row $row): ?array
    {
        $columns = [];
        foreach ($row->cells as $index => $cell) {
            $value = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell->getValue();
            $columns[$this->headerKey($this->text($value))] = $index;
        }

        $reportColumns = [];
        foreach (MscReportFields::INTERNAL_HEADERS as $label) {
            $key = $this->headerKey($label);
            if (! isset($columns[$key])) {
                $reportColumns = [];

                break;
            }
            $reportColumns[$key] = $columns[$key];
        }
        if (count($reportColumns) === count(MscReportFields::INTERNAL_HEADERS)) {
            return ['foreign_trade' => true, 'columns' => $reportColumns];
        }

        $baseColumns = [];
        foreach (MscFields::HEADERS as $field => $label) {
            if (in_array($field, ['no', 'bl_house', 'units_qty', 'status'], true)) {
                continue;
            }
            $key = $this->headerKey($label);
            $index = $columns[$key] ?? null;
            if ($field === 'bl_master') {
                $index ??= $columns['billofladingno'] ?? $columns['billoflading'] ?? null;
            }
            if ($field === 'container_qty') {
                $index ??= $columns['containerqty'] ?? null;
            }
            if ($field === 'vessel') {
                $index ??= $columns['shipagent'] ?? null;
            }
            if ($index === null) {
                return null;
            }
            $baseColumns[$field] = $index;
        }

        foreach (['units_qty' => 'units_qty', 'status' => 'status', 'bl_house' => 'bl_house'] as $field => $label) {
            $key = $this->headerKey(MscFields::HEADERS[$label]);
            if (isset($columns[$key])) {
                $baseColumns[$field] = $columns[$key];
            }
        }
        foreach (['categoria', 'category'] as $categoryHeader) {
            if (isset($columns[$categoryHeader])) {
                $baseColumns['category'] = $columns[$categoryHeader];

                break;
            }
        }

        return ['foreign_trade' => false, 'columns' => $baseColumns];
    }

    /**
     * @param  array<string, int>  $header
     * @param  array<string, mixed>  $catalogs
     * @param  array<string, MscShipment>  $shipments
     * @param  array<string, true>  $importedKeys
     * @param  array<string, true>  $updatedKeys
     */
    private function importInternationalRow(
        Row $row,
        int $number,
        array $header,
        array $catalogs,
        array &$shipments,
        array &$importedKeys,
        array &$updatedKeys,
        bool $dryRun,
        bool $foreignTrade,
        int &$skipped,
    ): void {
        $raw = $this->rowValues($row, $header);
        $blMaster = $this->text($raw['bl_master'] ?? null);
        $blHouse = $this->text($raw['bl_house'] ?? null);
        if ($blHouse === '' && preg_match('/\r|\n/', $blMaster)) {
            [$blMaster, $blHouse] = $this->splitBillOfLading($blMaster);
        }
        $model = $this->text($raw['model'] ?? null);
        if ($blMaster === '' && $this->text($raw['etd'] ?? null) === '' && $this->text($raw['eta'] ?? null) === '' && ($model === '' || in_array($this->normalize($model), ['total', 'totales'], true))) {
            $skipped++;

            return;
        }

        $existing = $shipments[$this->billOfLadingKey($blMaster, $blHouse)] ?? null;
        if ($existing?->exists && $existing->productLines()->count() > 1) {
            throw new InvalidArgumentException("Fila {$number}: este embarque contiene varios productos. Edita sus líneas en Comercio Exterior → MSC; la plantilla de un producto no puede reemplazarlas.");
        }
        $product = $catalogs['products'][$this->normalize($model)] ?? null;
        if ($product === null) {
            throw new InvalidArgumentException("Fila {$number}: el producto «{$model}» no existe. Regístralo en Productos antes de importar. No se guardaron cambios.");
        }

        $freeTime = $this->text($raw['free_time'] ?? null);
        if (preg_match('/^(\d+)\s*(?:days?|d[ií]as?)?$/iu', $freeTime, $matches)) {
            $freeTime = $matches[1];
        }
        $unitsQty = $this->text($raw['units_qty'] ?? null);
        $status = $this->text($raw['status'] ?? null);
        $data = [
            'etd' => $this->date($raw['etd'] ?? null),
            'eta' => $this->date($raw['eta'] ?? null),
            'bl_master' => $blMaster,
            'bl_house' => $blHouse,
            'factory_id' => $catalogs['factories'][$this->normalize($this->text($raw['factory'] ?? null))] ?? null,
            'vessel_id' => $catalogs['vessels'][$this->normalize($this->text($raw['vessel'] ?? null))] ?? null,
            'product_id' => $product['id'],
            'container_qty' => $this->text($raw['container_qty'] ?? null),
            'units_qty' => $unitsQty === '' ? null : $unitsQty,
            'status' => $status === '' ? null : $status,
            'free_time' => $freeTime,
            'released' => $this->text($raw['released'] ?? null),
        ];

        if ($foreignTrade && isset($raw['category'])) {
            $categoryName = $this->text($raw['category']);
            $categoryId = $categoryName === '' ? null : ($catalogs['categories'][$this->normalize($categoryName)] ?? null);
            if ($categoryName !== '' && $categoryId === null) {
                throw new InvalidArgumentException("Fila {$number}: la categoría «{$categoryName}» no existe. Regístrala en Categorías antes de importar. No se guardaron cambios.");
            }
            $data['category_id'] = $categoryId;
        }

        $rules = MscFields::rules(foreignTrade: $foreignTrade && array_key_exists('category', $raw));
        $rules['bl_master'] = ['required', 'string', 'max:255'];
        $validator = Validator::make($data, $rules, attributes: MscFields::labels());
        if ($validator->fails()) {
            throw new InvalidArgumentException("Fila {$number}: ".$validator->errors()->first().' No se guardaron cambios.');
        }
        $this->validateCapacity($number, $data, $product);

        $key = $this->billOfLadingKey($blMaster, $blHouse);
        $shipment = $shipments[$key] ?? null;
        if ($shipment === null) {
            if (! $dryRun) {
                $shipment = MscShipment::create($data);
                $shipment->syncSingleProductLine();
            } else {
                $shipment = new MscShipment($data);
            }
            $shipments[$key] = $shipment;
            $importedKeys[$key] = true;

            return;
        }

        $shipment->fill($data);
        if (! $dryRun && $shipment->exists) {
            $shipment->save();
            $shipment->syncSingleProductLine();
        }
        if (! isset($importedKeys[$key])) {
            $updatedKeys[$key] = true;
        }
    }

    /**
     * @param  array<string, int>  $header
     * @param  array<string, mixed>  $catalogs
     * @param  array<string, MscShipment>  $shipments
     * @param  array<string, true>  $importedKeys
     * @param  array<string, true>  $updatedKeys
     */
    private function importForeignTradeRow(
        Row $row,
        int $number,
        array $header,
        array $catalogs,
        array &$shipments,
        array &$importedKeys,
        array &$updatedKeys,
        bool $dryRun,
        int &$skipped,
    ): void {
        $raw = $this->rowValues($row, $header);
        $blMaster = $this->text($raw[$this->headerKey('BL Master')] ?? null);
        $blHouse = $this->text($raw[$this->headerKey('BL House')] ?? null);
        if ($blMaster === '' && $blHouse === '' && count(array_filter($raw, fn (mixed $value): bool => $this->text($value) !== '')) === 0) {
            $skipped++;

            return;
        }
        if ($blMaster === '') {
            throw new InvalidArgumentException("Fila {$number}: BL Master es obligatorio para identificar el embarque. No se guardaron cambios.");
        }

        $key = $this->billOfLadingKey($blMaster, $blHouse);
        $shipment = $shipments[$key] ?? null;
        if ($shipment === null) {
            throw new InvalidArgumentException("Fila {$number}: el embarque no existe. Para crear uno, completa primero la pestaña Comercio Internacional con el mismo BL Master y BL House. No se guardaron cambios.");
        }
        if ($shipment->exists && $shipment->productLines()->count() > 1) {
            throw new InvalidArgumentException("Fila {$number}: este embarque contiene varios productos. Edita sus líneas en Comercio Exterior → MSC; la plantilla de un producto no puede reemplazarlas.");
        }

        $data = ['bl_master' => $blMaster, 'bl_house' => $blHouse];
        $fieldHeaders = [
            'exp_number' => 'N°EXP.',
            'dua_intr' => 'DUA/INTR',
            'seabridge' => 'SEABRIDGE',
            'sencamer' => 'SENCAMER',
            'comex' => 'COMEX',
            'status_bl' => 'ESTATUS BL',
            'status' => 'STATUS',
            'container_qty' => 'CANT.',
            'units_qty' => 'UNID TOTALES',
            'released' => 'SERVICIO FLETE',
            'shipment_year' => 'AÑO',
            'etd' => 'ETD',
            'eta_vnzl' => 'ETA VZLA',
            'port' => 'PUERTO',
            'trasegado' => 'TRASEGADO',
            'in_bond_date' => 'IN BOND',
            'free_days' => 'DIAS LIBRES',
            'delay_date' => 'DEMORA',
            'legal_abandonment_date' => 'ABANDONO LEGAL',
            'registration_date' => 'REGISTRO',
            'phase_two_recognition_date' => 'RECONOCIMIENTO',
            'validation_date' => 'VALIDACION',
            'plant_receipt_date' => 'RECEPCIÓN PLANTA',
            'location' => 'UBICACIÓN',
            'return_date' => 'DEVOLUCION',
            'original_docs_receipt_date' => 'FECHA RECEP DE DOC ORIG',
            'aa_instructions_sent_date' => 'INSTRUCCIONES ENVIADAS AA',
            'bl_release_date' => 'FECHA LIBERACION BL',
            'sencamer_request_date' => 'FECHA DE SOLICITUD DE SENCAMER',
            'sencamer_receipt_date' => 'FECHA RECEPCION DE SENCAMER',
            'comex_request_date' => 'FECHA SOLICITUD DE COMEX',
            'comex_receipt_date' => 'FECHA RECEPCION DE COMEX',
            'customs_agent_status' => 'STATUS AGENTE DE ADUANA',
            'customs_agent_observation' => 'OBSERVACION AGENTE DE ADUANA',
        ];
        foreach ($fieldHeaders as $field => $label) {
            $value = $this->text($raw[$this->headerKey($label)] ?? null);
            if ($value === '') {
                continue;
            }
            $data[$field] = in_array($field, [
                'etd', 'eta_vnzl', 'in_bond_date', 'delay_date', 'legal_abandonment_date',
                'registration_date', 'phase_two_recognition_date', 'validation_date',
                'plant_receipt_date', 'return_date', 'original_docs_receipt_date',
                'aa_instructions_sent_date', 'bl_release_date', 'sencamer_request_date',
                'sencamer_receipt_date', 'comex_request_date', 'comex_receipt_date',
            ], true) ? $this->date($value) : $value;
        }

        foreach ([
            'category' => 'TIPO',
            'model' => 'MODELO/CONDICION',
            'factory' => 'PROVEEDOR',
            'vessel' => 'BUQUE',
        ] as $field => $label) {
            $value = $this->text($raw[$this->headerKey($label)] ?? null);
            if ($value === '') {
                continue;
            }
            $catalog = match ($field) {
                'category' => $catalogs['categories'],
                'model' => $catalogs['products'],
                'factory' => $catalogs['factories'],
                'vessel' => $catalogs['vessels'],
            };
            $match = $catalog[$this->normalize($value)] ?? null;
            if ($match === null) {
                $catalogLabel = $field === 'category' ? 'categoría' : ($field === 'model' ? 'producto' : ($field === 'factory' ? 'proveedor' : 'buque'));
                throw new InvalidArgumentException("Fila {$number}: el {$catalogLabel} «{$value}» no existe o no es único en el catálogo. No se guardaron cambios.");
            }
            $data[match ($field) {
                'category' => 'category_id',
                'model' => 'product_id',
                'factory' => 'factory_id',
                'vessel' => 'vessel_id',
            }] = is_array($match) ? $match['id'] : $match;
        }

        $containerValue = $this->text($raw[$this->headerKey('CONTENEDOR')] ?? null);
        $colorValue = $this->text($raw[$this->headerKey('COLORES')] ?? null);
        $categoryId = $data['category_id'] ?? $shipment->category_id;
        if ($colorValue !== '' && Category::find($categoryId)?->allows_multiple_products) {
            throw new InvalidArgumentException("Fila {$number}: las categorías de múltiples productos no permiten variantes. Retira el contenido de COLORES antes de importar.");
        }
        $validatorRules = MscFields::rules($shipment->id, foreignTrade: true, blHouse: $blHouse);
        $validatorRules = array_intersect_key($validatorRules, $data);
        $validator = Validator::make($data, $validatorRules, attributes: MscFields::labels());
        if ($validator->fails()) {
            throw new InvalidArgumentException("Fila {$number}: ".$validator->errors()->first().' No se guardaron cambios.');
        }

        $productId = (int) ($data['product_id'] ?? $shipment->product_id);
        $product = $catalogs['productsById'][$productId] ?? null;
        $this->validateCapacity($number, $data, $product);
        $containerLines = $containerValue === '' ? null : $this->containerNames($containerValue);
        $variantLines = $colorValue === '' ? null : $this->variantLines($number, $colorValue, $productId, $catalogs['variants']);

        $shipment->fill($data);
        if (! $dryRun) {
            $shipment->save();
            $shipment->syncSingleProductLine();
            if ($containerLines !== null) {
                $shipment->containers()->delete();
                if ($containerLines !== []) {
                    $shipment->containers()->createMany(
                        array_map(fn (string $name): array => ['name' => $name], $containerLines),
                    );
                }
            }
            if ($variantLines !== null) {
                $shipment->productVariantLines()->delete();
                if ($variantLines !== []) {
                    $shipment->productVariantLines()->createMany($variantLines);
                }
            }
        }
        $shipments[$key] = $shipment;
        if (! isset($importedKeys[$key])) {
            $updatedKeys[$key] = true;
        }
    }

    /** @param array<string, int> $header @return array<string, mixed> */
    private function rowValues(Row $row, array $header): array
    {
        $values = [];
        foreach ($header as $field => $index) {
            $cell = $row->cells[$index] ?? null;
            $values[$field] = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell?->getValue();
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{id: int, units_per_container: int|null}|null  $product
     */
    private function validateCapacity(int $number, array $data, ?array $product): void
    {
        if ($product === null || $product['units_per_container'] === null || ! isset($data['container_qty'])) {
            return;
        }

        $capacity = $product['units_per_container'] * (int) $data['container_qty'];
        if ($capacity > 2147483647) {
            throw new InvalidArgumentException("Fila {$number}: la capacidad calculada supera el máximo permitido. No se guardaron cambios.");
        }
        if (isset($data['units_qty']) && (int) $data['units_qty'] > $capacity) {
            throw new InvalidArgumentException("Fila {$number}: Cant Unidades no puede superar la capacidad de los contenedores. No se guardaron cambios.");
        }
    }

    /** @return list<string> */
    private function containerNames(string $value): array
    {
        $names = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return array_values(array_filter(
            array_map(fn (string $name): string => trim($name), $names),
            fn (string $name): bool => $name !== '',
        ));
    }

    /**
     * @param  array<string, int>  $variants
     * @return list<array{product_variant_id: int, quantity: int}>
     */
    private function variantLines(int $number, string $value, int $productId, array $variants): array
    {
        $lines = [];
        $seen = [];
        foreach (explode('//', $value) as $entry) {
            $entry = trim($entry);
            if (! preg_match('/^(?:(\d+)\s+)?(.+)$/u', $entry, $matches)) {
                throw new InvalidArgumentException("Fila {$number}: el formato de COLORES no es válido. Usa «12 rojo // negro». No se guardaron cambios.");
            }
            $quantity = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
            $color = trim($matches[2]);
            $key = $productId."\0".$this->normalize($color);
            $variantId = $variants[$key] ?? null;
            if ($variantId === null) {
                throw new InvalidArgumentException("Fila {$number}: la variante «{$color}» no existe para el producto seleccionado. No se guardaron cambios.");
            }
            if (isset($seen[$variantId])) {
                throw new InvalidArgumentException("Fila {$number}: la variante «{$color}» está repetida en COLORES. No se guardaron cambios.");
            }
            $seen[$variantId] = true;
            $lines[] = ['product_variant_id' => $variantId, 'quantity' => $quantity];
        }

        return $lines;
    }

    private function headerKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(Str::squish($value)));
    }

    private function billOfLadingKey(string $blMaster, string $blHouse): string
    {
        return $this->normalize($blMaster)."\0".$this->normalize($blHouse);
    }

    /** @return array{string, string} */
    private function splitBillOfLading(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));

        return [$lines[0] ?? '', isset($lines[1]) ? implode("\n", array_slice($lines, 1)) : ''];
    }

    private function text(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $text = $this->text($value);
        if (is_numeric($text) && (float) $text >= 1 && (float) $text <= 100000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $text)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date !== false && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return $text;
    }
}
