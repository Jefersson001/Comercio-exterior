<?php

namespace App\Actions\Msc;

use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\Vessel;
use App\Support\MscFields;
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
    /** @return array{imported: int, skipped: int} */
    public function handle(string $path, bool $dryRun = false, bool $foreignTrade = false): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);
        try {
            return DB::transaction(function () use ($reader, $dryRun, $foreignTrade): array {
                $products = [];
                foreach (Product::query()->get(['id', 'name', 'units_per_container']) as $product) {
                    $key = $this->normalize($product->name);
                    if (isset($products[$key])) {
                        throw new InvalidArgumentException('Hay productos con nombres equivalentes. Revisa el catálogo antes de importar MSC.');
                    }
                    $products[$key] = ['id' => $product->id, 'units_per_container' => $product->units_per_container];
                }
                $categories = [];
                if ($foreignTrade) {
                    foreach (Category::query()->get(['id', 'name']) as $category) {
                        $key = $this->normalize($category->name);
                        if (isset($categories[$key])) {
                            throw new InvalidArgumentException('Hay categorías con nombres equivalentes. Revisa el catálogo antes de importar.');
                        }
                        $categories[$key] = $category->id;
                    }
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
                $existing = MscShipment::query()->get(['bl_master', 'bl_house'])
                    ->mapWithKeys(fn (MscShipment $shipment): array => [$this->billOfLadingKey($shipment->bl_master, $shipment->bl_house) => true])->all();
                $header = null;
                $imported = 0;
                $skipped = 0;
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        if ($header === null) {
                            $header = $this->findHeader($row, $foreignTrade);
                            if ($header === null && $number >= 20) {
                                throw new InvalidArgumentException('No se encontró el encabezado MSC. Usa la plantilla del sistema.');
                            }

                            continue;
                        }
                        $raw = [];
                        foreach ($header as $field => $index) {
                            $cell = $row->cells[$index] ?? null;
                            $raw[$field] = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell?->getValue();
                        }
                        $blMaster = $this->text($raw['bl_master']);
                        $blHouse = $this->text($raw['bl_house'] ?? null);
                        if ($blHouse === '' && preg_match('/\r|\n/', $blMaster)) {
                            [$blMaster, $blHouse] = $this->splitBillOfLading($blMaster);
                        }
                        $model = $this->text($raw['model']);
                        if ($blMaster === '' && $this->text($raw['etd']) === '' && $this->text($raw['eta']) === '' && ($model === '' || in_array($this->normalize($model), ['total', 'totales'], true))) {
                            $skipped++;

                            continue;
                        }
                        $product = $products[$this->normalize($model)] ?? null;
                        if ($product === null) {
                            throw new InvalidArgumentException("Fila {$number}: el producto «{$model}» no existe. Regístralo en Productos antes de importar. No se guardaron cambios.");
                        }
                        $productId = $product['id'];
                        $freeTime = $this->text($raw['free_time']);
                        if (preg_match('/^(\d+)\s*(?:days?|d[ií]as?)?$/iu', $freeTime, $matches)) {
                            $freeTime = $matches[1];
                        }
                        $unitsQty = $this->text($raw['units_qty'] ?? null);
                        $status = $this->text($raw['status'] ?? null);
                        $data = [
                            'etd' => $this->date($raw['etd']), 'eta' => $this->date($raw['eta']),
                            'bl_master' => $blMaster, 'bl_house' => $blHouse,
                            'factory_id' => $factories[$this->normalize($this->text($raw['factory']))] ?? null,
                            'vessel_id' => $vessels[$this->normalize($this->text($raw['vessel']))] ?? null,
                            'product_id' => $productId, 'container_qty' => $this->text($raw['container_qty']),
                            'units_qty' => $unitsQty === '' ? null : $unitsQty,
                            'status' => $status === '' ? null : $status,
                            'free_time' => $freeTime,
                            'released' => $this->text($raw['released']),
                        ];
                        if ($foreignTrade) {
                            $categoryName = $this->text($raw['category'] ?? null);
                            $categoryId = $categoryName === '' ? null : ($categories[$this->normalize($categoryName)] ?? null);
                            if ($categoryName !== '' && $categoryId === null) {
                                throw new InvalidArgumentException("Fila {$number}: la categoría «{$categoryName}» no existe. Regístrala en Categorías antes de importar. No se guardaron cambios.");
                            }
                            $data['category_id'] = $categoryId;
                        }
                        $rules = MscFields::rules(foreignTrade: $foreignTrade);
                        $rules['bl_master'] = ['required', 'string', 'max:255'];
                        $validator = Validator::make($data, $rules, attributes: MscFields::labels());
                        if ($validator->fails()) {
                            throw new InvalidArgumentException("Fila {$number}: ".$validator->errors()->first().' No se guardaron cambios.');
                        }
                        if ($product['units_per_container'] !== null) {
                            $capacity = $product['units_per_container'] * (int) $data['container_qty'];
                            if ($capacity > 2147483647) {
                                throw new InvalidArgumentException("Fila {$number}: la capacidad calculada supera el máximo permitido. No se guardaron cambios.");
                            }
                            if ($unitsQty !== '' && (int) $unitsQty > $capacity) {
                                throw new InvalidArgumentException("Fila {$number}: Cant Unidades no puede superar la capacidad de los contenedores. No se guardaron cambios.");
                            }
                        }
                        $key = $this->billOfLadingKey($blMaster, $blHouse);
                        if (isset($existing[$key])) {
                            $skipped++;

                            continue;
                        }
                        if (! $dryRun) {
                            MscShipment::create($data);
                        }
                        $existing[$key] = true;
                        $imported++;
                    }
                    break;
                }
                if ($header === null) {
                    throw new InvalidArgumentException('El archivo no contiene los encabezados MSC. Usa la plantilla del sistema.');
                }
                if ($imported === 0 && $skipped === 0) {
                    throw new InvalidArgumentException('El archivo no contiene registros MSC.');
                }

                return ['imported' => $imported, 'skipped' => $skipped];
            });
        } finally {
            $reader->close();
        }
    }

    /** @return array<string, int>|null */
    private function findHeader(Row $row, bool $foreignTrade): ?array
    {
        $columns = [];
        foreach ($row->cells as $index => $cell) {
            $key = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($this->text($cell->getValue()))));
            $columns[$key] = $index;
        }
        $header = [];
        foreach (MscFields::HEADERS as $field => $label) {
            if (in_array($field, ['no', 'bl_house', 'units_qty', 'status'], true)) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($label)));
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
            $header[$field] = $index;
        }

        $unitsKey = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(MscFields::HEADERS['units_qty'])));
        if (isset($columns[$unitsKey])) {
            $header['units_qty'] = $columns[$unitsKey];
        }

        $statusKey = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(MscFields::HEADERS['status'])));
        if (isset($columns[$statusKey])) {
            $header['status'] = $columns[$statusKey];
        }

        $houseKey = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(MscFields::HEADERS['bl_house'])));
        if (isset($columns[$houseKey])) {
            $header['bl_house'] = $columns[$houseKey];
        }

        if ($foreignTrade) {
            $categoryIndex = $columns['categoria'] ?? $columns['category'] ?? null;
            if ($categoryIndex !== null) {
                $header['category'] = $categoryIndex;
            }
        }

        return $header;
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
