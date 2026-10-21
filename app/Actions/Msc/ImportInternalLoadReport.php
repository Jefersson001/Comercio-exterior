<?php

namespace App\Actions\Msc;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\Vessel;
use App\Support\HistoryRecorder;
use App\Support\MscFields;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Legacy internal report: combined models stay together; BL order is Master then House.
 * This deliberately does not change the standard template importer.
 */
class ImportInternalLoadReport
{
    public const string SHEET = 'CONTROL DE CARGAS INTERNO';

    /** @return array<string, mixed> */
    public function handle(string $path, bool $dryRun = true, string $sourceLabel = self::SHEET): array
    {
        $rows = $this->read($path);
        $result = ['dry_run' => $dryRun, 'rows' => count($rows), 'created' => [], 'updated' => 0, 'unchanged' => 0, 'warnings' => []];
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            app(HistoryRecorder::class)->batch(function () use ($rows, $sourceLabel, &$result): void {
                $catalogs = [];
                foreach (['category' => Category::class, 'product' => Product::class, 'factory' => Factory::class, 'vessel' => Vessel::class] as $type => $class) {
                    $catalogs[$type] = [];
                    $result['created'][$type] = 0;
                    foreach ($class::all() as $record) {
                        $key = $this->normalize($record->name);
                        if (isset($catalogs[$type][$key])) {
                            throw new InvalidArgumentException("El catálogo de {$type} tiene nombres equivalentes: {$record->name}.");
                        }
                        $catalogs[$type][$key] = $record;
                    }
                }
                $result['created']['msc'] = 0;
                $existing = [];
                foreach (MscShipment::all() as $shipment) {
                    $key = $this->blKey($shipment->bl_master, $shipment->bl_house);
                    if (isset($existing[$key])) {
                        throw new InvalidArgumentException('Hay embarques existentes con BL equivalentes.');
                    }
                    $existing[$key] = $shipment;
                }
                $seen = [];
                foreach ($rows as $number => $row) {
                    try {
                        $parts = preg_split('/\s+/u', $this->text($row[10]), flags: PREG_SPLIT_NO_EMPTY) ?: [];
                        if (count($parts) < 1 || count($parts) > 2) {
                            throw new InvalidArgumentException('BL debe contener una o dos referencias: Master y después House.');
                        }
                        [$master, $house] = [$parts[0], $parts[1] ?? ''];
                        $key = $this->blKey($master, $house);
                        if (isset($seen[$key])) {
                            throw new InvalidArgumentException("BL repetido en las filas {$seen[$key]} y {$number}; revisa antes de importar.");
                        }
                        $seen[$key] = $number;
                        $category = $this->catalog('category', Category::class, $row[15], [], $catalogs, $result);
                        $product = $this->catalog('product', Product::class, $row[16], ['category_id' => $category->id], $catalogs, $result);
                        if ($product->category_id !== $category->id) {
                            throw new InvalidArgumentException("El producto {$product->name} ya pertenece a otra categoría.");
                        }
                        $factory = $this->text($row[17]) === '' ? null : $this->catalog('factory', Factory::class, $row[17], [], $catalogs, $result);
                        $vessel = $this->text($row[19]) === '' ? null : $this->catalog('vessel', Vessel::class, $row[19], [], $catalogs, $result);
                        $notes = ['Origen: '.$sourceLabel.($sourceLabel === self::SHEET ? ' · fila ' : ' · fila de carga ').$number, 'BL original: '.$this->text($row[10])];
                        $warn = function (string $message) use ($number, &$result): void {
                            $result['warnings'][] = ['row' => $number, 'message' => $message];
                        };
                        $units = $this->integer($row[18], 'UNID TOTALES', nullable: true);
                        if ($units === 0) {
                            $units = null;
                            $notes[] = 'UNID TOTALES original: 0 (cantidad sin determinar; no se infiere).';
                            $warn('Unidades originales 0: se conserva la observación y la cantidad queda sin determinar.');
                        }
                        if ($this->text($row[22]) !== '') {
                            $notes[] = 'COLORES original: '.$this->text($row[22]);
                            $warn('COLORES conservado en observaciones; no se crean variantes a partir de chasis o descripciones sin desglose.');
                        }
                        if ($vessel === null) {
                            $warn('Buque no informado en el origen.');
                        }
                        $statusText = $this->text($row[9]) ?: $this->text($row[8]);
                        $notes[] = 'STATUS original: '.$statusText;
                        $count = $this->integer($row[14], 'CANT.');
                        $days = $this->integer($row[28], 'DIAS LIBRES', nullable: true);
                        if ($days === null) {
                            $notes[] = 'DIAS LIBRES no informado; Free time usa 0 hasta completar el dato.';
                            $warn('Días libres sin informar; Free time se registra como 0.');
                        }
                        $data = [
                            'exp_number' => $this->text($row[0]), 'dua_intr' => $this->text($row[1]),
                            'seabridge' => $this->text($row[2]), 'sencamer' => $this->text($row[3]), 'comex' => $this->text($row[4]),
                            'bl_master' => $master, 'bl_house' => $house,
                            'category_id' => $category->id, 'product_id' => $product->id,
                            'factory_id' => $factory?->id, 'vessel_id' => $vessel?->id,
                            'container_qty' => $count, 'units_qty' => $units, 'free_time' => $days ?? 0, 'free_days' => $days,
                            'released' => $this->text($row[20]), 'shipment_year' => $this->text($row[21]) ?: null,
                            'status' => $this->shipmentStatus($statusText),
                            'status_bl' => $this->blStatus($this->text($row[5])),
                            'port' => $this->text($row[25]), 'trasegado' => $this->text($row[26]),
                            'location' => $this->text($row[35]), 'customs_agent_status' => $this->text($row[44]),
                        ];
                        foreach ([
                            23 => 'etd', 24 => 'eta', 27 => 'in_bond_date', 29 => 'delay_date', 30 => 'legal_abandonment_date',
                            31 => 'registration_date', 32 => 'phase_two_recognition_date', 33 => 'validation_date', 34 => 'plant_receipt_date',
                            36 => 'return_date', 37 => 'original_docs_receipt_date', 38 => 'aa_instructions_sent_date', 39 => 'bl_release_date',
                            40 => 'sencamer_request_date', 41 => 'sencamer_receipt_date', 42 => 'comex_request_date', 43 => 'comex_receipt_date',
                        ] as $index => $field) {
                            $value = $this->text($row[$index]);
                            if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                                $data[$field] = $value ?: null;
                            } else {
                                $notes[] = MscFields::labels()[$field].' original: '.$value;
                                $data[$field] = null;
                                $warn("Texto conservado en observaciones para {$field}: {$value}");
                            }
                        }
                        $data['etd_vnzl'] = $data['etd'] ?? null;
                        $data['eta_vnzl'] = $data['eta'] ?? null;
                        if (preg_match('/RECEPCION\s+(\d{2})-(\d{2})-(\d{4})/', $this->normalize($statusText), $date)) {
                            $data['receipt_date'] = "{$date[3]}-{$date[2]}-{$date[1]}";
                        }
                        preg_match_all('/[A-Z]{4}\s*\d{7}/', Str::upper($this->text($row[13])), $matches);
                        $containers = array_values(array_unique(array_map(fn ($name) => preg_replace('/\s+/', '', $name), $matches[0])));
                        if (count($containers) !== $count) {
                            $warn('Los contenedores identificados no coinciden con CANT.; se mantiene la cantidad original.');
                        }
                        if ($this->text($row[13]) !== '') {
                            $notes[] = 'CONTENEDOR original: '.$this->text($row[13]);
                        }
                        $notes[] = 'La capacidad por contenedor no se infiere de los totales de este archivo.';
                        $data['customs_agent_observation'] = implode("\n", array_filter([$this->text($row[45]), ...$notes]));
                        $shipment = $existing[$key] ?? new MscShipment;
                        if ($shipment->exists && $shipment->productLines()->count() > 1) {
                            throw new InvalidArgumentException('El embarque existente tiene varias líneas; no se reemplazarán por una combinación.');
                        }
                        if ($units === null && $product->units_per_container !== null) {
                            throw new InvalidArgumentException('El origen no determina unidades y el producto existente tiene capacidad automática; revisa para evitar inventar una cantidad.');
                        }
                        $rules = MscFields::rules($shipment->id, foreignTrade: true, blHouse: $house);
                        Validator::make($data, $rules, attributes: MscFields::labels())->validate();
                        if ($product->units_per_container !== null && $units > $product->units_per_container * $count) {
                            throw new InvalidArgumentException('Las unidades superan la capacidad del producto existente.');
                        }
                        $created = ! $shipment->exists;
                        $shipment->fill($data);
                        $line = ['product_id' => $product->id, 'container_qty' => $count, 'units_qty' => $units];
                        $currentLines = $shipment->exists ? $shipment->productLines()->get(['product_id', 'container_qty', 'units_qty'])->toArray() : [];
                        $currentContainers = $shipment->exists ? $shipment->containers()->pluck('name')->all() : [];
                        $changed = $created || $shipment->isDirty() || $currentLines !== [$line] || $currentContainers !== $containers;
                        if ($changed) {
                            $shipment->save();
                            if ($currentLines !== [$line]) {
                                $shipment->syncSingleProductLine();
                            }
                            if ($currentContainers !== $containers) {
                                $shipment->containers()->delete();
                                $shipment->containers()->createMany(array_map(fn ($name): array => ['name' => $name], $containers));
                            }
                        }
                        if ($created) {
                            $result['created']['msc']++;
                        } elseif ($changed) {
                            $result['updated']++;
                        } else {
                            $result['unchanged']++;
                        }
                        $existing[$key] = $shipment;
                    } catch (Throwable $exception) {
                        throw new InvalidArgumentException("Fila {$number}: ".$exception->getMessage(), previous: $exception);
                    }
                }
            }, 'import');
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $result;
    }

    /** @return array<int, list<mixed>> */
    private function read(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        $rows = [];
        $header = false;
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if (trim($sheet->getName()) !== self::SHEET) {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $number => $row) {
                    $values = [];
                    foreach ($row->cells as $cell) {
                        $value = match (true) {
                            $cell instanceof FormulaCell => $cell->getComputedValue(),
                            $cell instanceof TextRunCell => $cell->getStringValue(),
                            default => $cell->getValue(),
                        };
                        $values[] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
                    }
                    $values = array_pad($values, 46, null);
                    if (! $header) {
                        if ($this->normalize($values[10]) !== 'BL') {
                            continue;
                        }
                        foreach ([0 => 'N°EXP.', 5 => 'ESTATUS BL', 9 => 'STATUS', 13 => 'CONTENEDOR', 14 => 'CANT.', 15 => 'TIPO', 16 => 'MODELO/CONDICION', 17 => 'PROVEEDOR', 18 => 'UNID TOTALES', 19 => 'BUQUE', 22 => 'COLORES', 23 => 'ETD', 24 => 'ETA VZLA', 45 => 'OBSERVACION AGENTE DE ADUANA'] as $index => $label) {
                            if ($this->normalize($values[$index]) !== $this->normalize($label)) {
                                throw new InvalidArgumentException('Encabezado inesperado en columna '.($index + 1).": se esperaba {$label}.");
                            }
                        }
                        $header = true;

                        continue;
                    }
                    // Report summaries have no expedition number or BL.
                    if ($this->text($values[0]) === '' && $this->text($values[10]) === '') {
                        continue;
                    }
                    foreach ($row->cells as $index => $cell) {
                        if ($cell instanceof FormulaCell && $cell->getComputedValue() === null) {
                            throw new InvalidArgumentException("Fila {$number}, columna ".($index + 1).': fórmula sin resultado almacenado; abre y recalcula el Excel.');
                        }
                    }
                    $rows[$number] = $values;
                }
            }
        } finally {
            $reader->close();
        }
        if (! $header || $rows === []) {
            throw new InvalidArgumentException('No se encontraron embarques en la hoja '.self::SHEET.'.');
        }

        return $rows;
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function normalize(mixed $value): string
    {
        return Str::upper(Str::ascii(preg_replace('/\s+/u', ' ', $this->text($value))));
    }

    private function blKey(?string $master, ?string $house): string
    {
        return json_encode([$this->normalize($master), $this->normalize($house)], JSON_THROW_ON_ERROR);
    }

    private function integer(mixed $value, string $label, bool $nullable = false): ?int
    {
        $value = $this->text($value);
        if ($value === '' && $nullable) {
            return null;
        }
        if (! preg_match('/^\d+$/D', $value) || (float) $value > 2147483647) {
            throw new InvalidArgumentException("{$label} debe ser un entero válido.");
        }

        return (int) $value;
    }

    private function shipmentStatus(string $value): string
    {
        $normalized = $this->normalize($value);
        if (str_starts_with($normalized, 'RECEPCION ')) {
            return MscShipmentStatus::Recepcion->value;
        }
        if ($normalized === 'TRANSITO') {
            return MscShipmentStatus::Navegando->value;
        }
        foreach (MscShipmentStatus::cases() as $status) {
            if ($this->normalize($status->value) === $normalized) {
                return $status->value;
            }
        }
        if ($normalized === '') {
            return MscShipmentStatus::Draft->value;
        }
        throw new InvalidArgumentException("Status de embarque desconocido: {$value}.");
    }

    private function blStatus(string $value): string
    {
        $normalized = $this->normalize($value);
        if ($normalized === 'POR LIBERACION BL SIN DOCUMENTOS') {
            return MscBillOfLadingStatus::PendingReleaseWithoutDocument->value;
        }
        foreach (MscBillOfLadingStatus::cases() as $status) {
            if ($this->normalize($status->value) === $normalized) {
                return $status->value;
            }
        }
        if ($normalized === '') {
            return MscBillOfLadingStatus::Draft->value;
        }
        throw new InvalidArgumentException("Status BL desconocido: {$value}.");
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, Model>>  $catalogs
     * @param  array<string, mixed>  $result
     * @return T
     */
    private function catalog(string $type, string $class, mixed $name, array $attributes, array &$catalogs, array &$result): Model
    {
        $name = preg_replace('/\s+/u', ' ', $this->text($name));
        if ($name === '' || Str::length($name) > 255) {
            throw new InvalidArgumentException("Nombre de {$type} vacío o demasiado largo.");
        }
        $key = $this->normalize($name);
        if (! isset($catalogs[$type][$key])) {
            $catalogs[$type][$key] = $class::create(['name' => $name, ...$attributes]);
            $result['created'][$type]++;
        }

        $record = $catalogs[$type][$key];
        if (! $record instanceof $class) {
            throw new \LogicException('El catálogo contiene un tipo de registro inesperado.');
        }

        return $record;
    }
}
