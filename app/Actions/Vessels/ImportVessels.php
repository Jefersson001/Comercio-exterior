<?php

namespace App\Actions\Vessels;

use App\Models\Vessel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class ImportVessels
{
    /** @return array{imported: int, skipped: int} */
    public function handle(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        try {
            return DB::transaction(function () use ($reader): array {
                $existing = Vessel::query()->pluck('name')
                    ->mapWithKeys(fn (string $name): array => [Str::lower(Str::ascii(Str::squish($name))) => true])->all();
                $headerRead = false;
                $dataRows = 0;
                $imported = 0;
                $skipped = 0;
                $batch = [];

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        $name = $this->readName($row, $number);

                        if (! $headerRead) {
                            if (! in_array(Str::lower($name), ['nombre', 'name'], true)) {
                                throw new InvalidArgumentException('La primera fila debe contener una sola columna llamada Nombre. Descarga la plantilla para usar el formato correcto.');
                            }
                            $headerRead = true;

                            continue;
                        }

                        $name = Str::squish($name);
                        if ($name === '') {
                            $skipped++;

                            continue;
                        }
                        if (Str::length($name) > 255) {
                            throw new InvalidArgumentException("Fila {$number}: el nombre no puede superar los 255 caracteres. No se importó ningún buque.");
                        }

                        $dataRows++;
                        $key = Str::lower(Str::ascii($name));
                        if (isset($existing[$key])) {
                            $skipped++;

                            continue;
                        }

                        $existing[$key] = true;
                        $batch[] = ['name' => $name, 'created_at' => now(), 'updated_at' => now()];
                        $imported++;

                        if (count($batch) === 500) {
                            Vessel::query()->insert($batch);
                            $batch = [];
                        }
                    }
                    break;
                }

                if (! $headerRead || $dataRows === 0) {
                    throw new InvalidArgumentException('El archivo no contiene buques. Completa la columna Nombre debajo del encabezado.');
                }

                if ($batch !== []) {
                    Vessel::query()->insert($batch);
                }

                return ['imported' => $imported, 'skipped' => $skipped];
            });
        } finally {
            $reader->close();
        }
    }

    private function readName(Row $row, int $number): string
    {
        foreach ($row->cells as $index => $cell) {
            if ($index > 0 && $cell->getValue() !== null && $cell->getValue() !== '') {
                throw new InvalidArgumentException("Fila {$number}: el archivo solo debe contener la columna Nombre. No se importó ningún buque.");
            }
        }

        $cell = $row->cells[0] ?? null;
        $value = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell?->getValue();
        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Fila {$number}: el nombre debe ser un texto. No se importó ningún buque.");
        }

        return (string) $value;
    }
}
