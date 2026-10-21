<?php

namespace App\Actions\Msc;

use App\Models\Category;
use App\Models\Factory;
use App\Models\Product;
use App\Models\Vessel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

class ImportDispatchedReport
{
    public const string SHEET = 'DESPACHADOS 2026';

    /** @return array<string, mixed> */
    public function handle(string $path, bool $dryRun = true, ?string $normalizedCopy = null): array
    {
        $rows = $this->read($path);
        $groups = [];
        $duplicates = [];
        foreach ($rows as $number => $row) {
            $parts = preg_split('/\s+/u', $this->text($row[8]), flags: PREG_SPLIT_NO_EMPTY) ?: [];
            $parts = array_values(array_filter($parts, fn ($part): bool => ! preg_match('/^RO\d+$/i', $part)));
            $parts = array_map(fn ($part): string => Str::upper(rtrim($part, ',;')), $parts);
            if ($parts === []) {
                throw new InvalidArgumentException("Fila {$number}: no hay BL ni celda combinada que permita recuperarlo.");
            }
            $key = json_encode(array_slice($parts, 0, 2), JSON_THROW_ON_ERROR);
            $fingerprint = json_encode(array_map(fn ($value): string => $this->key($value), $row), JSON_THROW_ON_ERROR);
            if (isset($groups[$key]['fingerprints'][$fingerprint])) {
                $duplicates[] = ['row' => $number, 'duplicate_of' => $groups[$key]['fingerprints'][$fingerprint]];

                continue;
            }
            $groups[$key]['parts'] = $parts;
            $groups[$key]['rows'][$number] = $row;
            $groups[$key]['fingerprints'][$fingerprint] = $number;
        }
        $catalogs = [];
        foreach (['category' => Category::class, 'product' => Product::class, 'factory' => Factory::class, 'vessel' => Vessel::class] as $type => $class) {
            foreach ($class::all() as $record) {
                $catalogs[$type][$this->key($record->name)] = $record->name;
            }
        }
        $products = Product::with('category')->get()->keyBy(fn ($product): string => $this->key($product->name));
        $normalized = [];
        $summary = [];
        foreach ($groups as $group) {
            $entries = $group['rows'];
            $first = reset($entries);
            $record = array_fill(0, 46, '');
            $notes = ['Origen real: '.self::SHEET.' · filas '.implode(', ', array_keys($entries))];
            $models = [];
            $categories = [];
            $factories = [];
            $vessels = [];
            $units = 0;
            $unknownUnits = false;
            $containers = 0;
            $codes = [];
            $etds = [];
            $etas = [];
            $dates = [];
            $expeditions = [];
            $freeDays = [];
            $blStatuses = [];
            foreach ($entries as $number => $entry) {
                $model = $this->text($entry[14]);
                $model = $catalogs['product'][$this->key($model)] ?? $model;
                $models[$this->key($model)] = $model;
                $sourceCategory = $this->text($entry[13]);
                $category = match ($this->key($sourceCategory)) {
                    'REP', 'REP VEH' => 'REPTOS',
                    'TRIMOTO' => 'TRIMOTO 200',
                    'ZONTES' => 'ZONTES MOTO',
                    'MFDB' => 'FB',
                    default => $sourceCategory,
                };
                $category = $products->get($this->key($model))?->category->name ?? $category;
                if ($category === '') {
                    throw new InvalidArgumentException("Fila {$number}: no se conoce la categoría y el producto no tiene una asignada.");
                }
                $categories[$this->key($category)] = $category;
                foreach (['factory' => 15, 'vessel' => 17] as $type => $index) {
                    $name = $this->text($entry[$index]);
                    if ($name !== '') {
                        $name = $catalogs[$type][$this->key($name)] ?? $name;
                        if ($type === 'factory') {
                            $factories[$this->key($name)] = $name;
                        } else {
                            $vessels[$this->key($name)] = $name;
                        }
                    }
                }
                $count = $this->quantity($entry[12], $number);
                $amount = $this->quantity($entry[16], $number);
                $containers += $count ?? 0;
                $units += $amount ?? 0;
                $unknownUnits = $unknownUnits || $amount === null || $amount === 0;
                preg_match_all('/[A-Z]{4}\s*\d{7}/', Str::upper($this->text($entry[11])), $matches);
                foreach ($matches[0] as $code) {
                    $code = preg_replace('/\s+/', '', $code);
                    $codes[$code] = $code;
                }
                $etd = $this->date($entry[21]);
                $eta = $this->date($entry[22]);
                if ($this->text($entry[21]) !== '' && $etd === null) {
                    throw new InvalidArgumentException("Fila {$number}: ETD no interpretable; no se reemplazará por otra fecha.");
                }
                if ($eta === null) {
                    throw new InvalidArgumentException("Fila {$number}: ETA no interpretable.");
                }
                if ($etd !== null) {
                    $etds[$etd] = $etd;
                }
                $etas[$eta] = $eta;
                foreach ([24 => 26, 25 => 27, 27 => 29, 28 => 31, 29 => 32, 30 => 33, 31 => 34, 33 => 36, 35 => 37, 36 => 38, 37 => 39, 38 => 40, 39 => 41, 40 => 42, 41 => 43] as $source => $target) {
                    $date = $this->date($entry[$source]);
                    if ($date !== null) {
                        $dates[$target][$date] = $date;
                    }
                }
                if ($this->text($entry[0]) !== '') {
                    $expeditions[$this->text($entry[0])] = $this->text($entry[0]);
                }
                $days = $this->quantity($entry[26], $number);
                if ($days !== null) {
                    $freeDays[$days] = $days;
                }
                // These historical columns changed meaning over the year. Only explicit BL text counts.
                foreach ([$entry[4], $entry[5]] as $value) {
                    if (str_contains($this->key($value), 'LIBERADO') && ! str_contains($this->key($value), 'NO LIBERADO')) {
                        $blStatuses[] = 'BL LIBERADO OK';
                    }
                }
                $detail = [];
                foreach ([
                    0 => 'Expediente', 1 => 'DUA', 2 => 'Agente', 3 => 'Documentos', 4 => 'DOC original',
                    5 => 'Columna auxiliar', 6 => 'STATUS auxiliar', 7 => 'STATUS original', 8 => 'BL original', 9 => 'RO',
                    11 => 'Contenedores originales', 12 => 'Cantidad contenedores', 13 => 'Categoría original',
                    14 => 'Modelo original', 15 => 'Proveedor original', 16 => 'Unidades originales', 17 => 'Buque original',
                    18 => 'Servicio', 19 => 'Año', 20 => 'Nota/colores', 21 => 'ETD original', 22 => 'ETA original',
                    23 => 'Puerto', 24 => 'Trasegado', 25 => 'In bond', 26 => 'Días libres', 27 => 'Demora',
                    28 => 'Registro', 29 => 'Reconocimiento', 30 => 'Validación', 31 => 'Recepción planta',
                    32 => 'Ubicación', 33 => 'Devolución', 34 => 'Observaciones originales', 35 => 'Recepción documentos',
                    36 => 'Instrucciones AA', 37 => 'Liberación BL', 38 => 'Solicitud SENCAMER',
                    39 => 'Recepción SENCAMER', 40 => 'Solicitud COMEX', 41 => 'Recepción COMEX',
                ] as $index => $label) {
                    if ($this->text($entry[$index]) !== '') {
                        $detail[] = $label.': '.$this->text($entry[$index]);
                    }
                }
                $notes[] = "Entrega / fila {$number}\n".implode(' | ', $detail);
            }
            if (count($etds) > 1 || count($etas) > 1) {
                throw new InvalidArgumentException('El BL '.implode(' ', $group['parts']).' tiene ETD o ETA incompatibles entre sus filas.');
            }
            if ($containers < 1) {
                throw new InvalidArgumentException('BL sin contenedores: '.implode(' ', $group['parts']));
            }
            $combined = implode(' // ', $models);
            $record[0] = implode(' / ', $expeditions);
            foreach ([1, 2, 18, 19, 23, 32] as $index) {
                $target = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 18 => 20, 19 => 21, 23 => 25, 32 => 35][$index];
                $texts = array_values(array_unique(array_filter(array_map(fn ($entry): string => $this->text($entry[$index]), $entries))));
                // Keep different original values in the per-delivery notes; use the latest supplied value in the summary.
                $record[$target] = $texts === [] ? '' : end($texts);
            }
            foreach ([3 => 'SENCAMER', 4 => 'COMEX'] as $index => $label) {
                foreach ($entries as $entry) {
                    if (str_contains($this->key($entry[$index]), $label)) {
                        $record[$index] = $this->text($entry[$index]);
                    }
                }
            }
            $record[5] = $blStatuses !== [] ? 'BL LIBERADO OK' : 'Borrador';
            $record[9] = 'RECEPCION';
            $record[10] = implode(' ', array_slice($group['parts'], 0, 2));
            $record[13] = implode(' ', $codes);
            $record[14] = $containers;
            $record[15] = reset($categories);
            $record[16] = $combined;
            $record[17] = $factories === [] ? '' : reset($factories);
            $record[18] = $unknownUnits ? 0 : $units;
            $record[19] = $vessels === [] ? '' : reset($vessels);
            $record[23] = $etds === [] ? '' : reset($etds);
            $record[24] = reset($etas);
            $record[28] = $freeDays === [] ? '' : end($freeDays);
            foreach ($dates as $target => $values) {
                sort($values);
                $record[$target] = end($values);
            }
            if (count($categories) > 1) {
                $notes[] = 'Producto conjunto de categorías '.implode(' / ', $categories).'; categoría principal: '.$record[15].'.';
            }
            if ($unknownUnits) {
                $notes[] = "Unidades conocidas de las entregas: {$units}; total pendiente por filas sin unidades determinadas.";
            }
            foreach ($duplicates as $duplicate) {
                if (isset($entries[$duplicate['duplicate_of']])) {
                    $notes[] = 'Fila '.$duplicate['row'].' excluida por ser idéntica a '.$duplicate['duplicate_of'].'.';
                }
            }
            $record[45] = implode("\n\n", $notes);
            $normalized[] = $record;
            $summary[] = ['master' => $group['parts'][0], 'house' => $group['parts'][1] ?? '', 'source_rows' => array_keys($entries), 'containers' => $containers, 'known_units' => $units, 'units' => $unknownUnits ? null : $units];
        }
        $temp = tempnam(sys_get_temp_dir(), 'msc-dispatched-');
        if ($temp === false) {
            throw new \RuntimeException('No se pudo preparar el lote de despachados.');
        }
        try {
            $writer = new Writer;
            $writer->openToFile($temp);
            try {
                $writer->getCurrentSheet()->setName(ImportInternalLoadReport::SHEET);
                $headers = array_replace(array_fill(0, 46, ''), [
                    0 => 'N°EXP.', 5 => 'ESTATUS BL', 9 => 'STATUS', 10 => 'BL', 13 => 'CONTENEDOR', 14 => 'CANT.',
                    15 => 'TIPO', 16 => 'MODELO/CONDICION', 17 => 'PROVEEDOR', 18 => 'UNID TOTALES', 19 => 'BUQUE',
                    22 => 'COLORES', 23 => 'ETD', 24 => 'ETA VZLA', 45 => 'OBSERVACION AGENTE DE ADUANA',
                ]);
                $writer->addRow(Row::fromValues($headers));
                foreach ($normalized as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            } finally {
                $writer->close();
            }
            if ($normalizedCopy !== null && ! copy($temp, $normalizedCopy)) {
                throw new \RuntimeException('No se pudo guardar la copia del lote normalizado.');
            }
            $result = app(ImportInternalLoadReport::class)->handle($temp, $dryRun, self::SHEET.' · lote normalizado');
            $result['source_rows'] = count($rows);
            $result['excluded_duplicates'] = $duplicates;
            $result['grouped_shipments'] = $summary;

            return $result;
        } finally {
            gc_collect_cycles();
            unlink($temp);
        }
    }

    /** @return array<int, list<mixed>> */
    private function read(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true, SHOULD_LOAD_MERGE_CELLS: true));
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if (trim($sheet->getName()) !== self::SHEET) {
                    continue;
                }
                $merge = [];
                foreach ($sheet->getMergeCells() as $range) {
                    if (preg_match('/^([ABI])(\d+):\1(\d+)$/', $range, $m)) {
                        $index = ['A' => 0, 'B' => 1, 'I' => 8][$m[1]];
                        for ($n = (int) $m[2] + 1; $n <= (int) $m[3]; $n++) {
                            $merge[$n][$index] = (int) $m[2];
                        }
                    }
                }
                $header = false;
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
                    $values = array_pad($values, 42, null);
                    if (! $header) {
                        if ($this->key($values[8]) !== 'BL') {
                            continue;
                        }
                        foreach ([12 => 'CANT.', 13 => 'TIPO', 14 => 'MODELO', 15 => 'PROVEEDOR', 16 => 'UNID TOTALES', 21 => 'ETD', 22 => 'ETA VZLA'] as $index => $label) {
                            if ($this->key($values[$index]) !== $this->key($label)) {
                                throw new InvalidArgumentException("Encabezado inesperado en columna {$index} de despachados.");
                            }
                        }
                        $header = true;

                        continue;
                    }
                    if ($this->text($values[14]) === '' && $this->text($values[8]) === '') {
                        continue;
                    }
                    foreach ($row->cells as $cell) {
                        if ($cell instanceof FormulaCell && $cell->getComputedValue() === null) {
                            throw new InvalidArgumentException("Fila {$number}: fórmula sin resultado almacenado.");
                        }
                    }
                    foreach ($merge[$number] ?? [] as $index => $start) {
                        if ($this->text($values[$index]) === '') {
                            $values[$index] = $rows[$start][$index] ?? null;
                        }
                    }
                    $rows[$number] = array_values($values);
                }
            }
        } finally {
            $reader->close();
        }
        if ($rows === []) {
            throw new InvalidArgumentException('No se encontraron registros de despachados.');
        }

        return $rows;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')));
    }

    private function key(mixed $value): string
    {
        return Str::upper(Str::ascii($this->text($value)));
    }

    private function quantity(mixed $value, int $row): ?int
    {
        $value = $this->text($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^\d+$/D', $value) || (float) $value > 2147483647) {
            throw new InvalidArgumentException("Fila {$row}: cantidad inválida {$value}.");
        }

        return (int) $value;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd/m/y', 'j/n/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
