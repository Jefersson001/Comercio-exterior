<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Support\HistoryRecorder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class ImportCategories
{
    /** @return array{imported: int, updated: int, skipped: int} */
    public function handle(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        try {
            return app(HistoryRecorder::class)->batch(function () use ($reader): array {
                $existing = Category::query()->get()->keyBy(fn (Category $category): string => Str::lower(trim($category->name)));
                $seen = [];
                $headerRead = false;
                $columnCount = 1;
                $dataRows = 0;
                $imported = 0;
                $updated = 0;
                $skipped = 0;
                $batch = [];

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        if (! $headerRead) {
                            $secondHeader = Str::lower(trim((string) (($row->cells[1] ?? null)?->getValue() ?? '')));
                            if ($secondHeader !== '') {
                                if (! in_array($secondHeader, ['es para múltiples productos', 'allows_multiple_products'], true)) {
                                    throw new InvalidArgumentException('La segunda columna debe llamarse Es para múltiples productos.');
                                }
                                $columnCount = 2;
                            }
                            $name = $this->readName($row, $number, $columnCount);
                            if (! in_array(Str::lower($name), ['nombre', 'name'], true)) {
                                throw new InvalidArgumentException('La primera columna debe llamarse Nombre y la segunda, si se incluye, Es para múltiples productos. Descarga la plantilla para usar el formato correcto.');
                            }

                            $headerRead = true;

                            continue;
                        }
                        $name = $this->readName($row, $number, $columnCount);
                        $multiple = $columnCount === 2 ? $this->readMultiple(($row->cells[1] ?? null)?->getValue(), $number) : null;

                        if ($name === '' && $multiple === null) {
                            $skipped++;

                            continue;
                        }
                        if ($name === '') {
                            throw new InvalidArgumentException("Fila {$number}: ingresa el nombre de la categoría.");
                        }

                        if (Str::length($name) > 255) {
                            throw new InvalidArgumentException("Fila {$number}: el nombre no puede superar los 255 caracteres. No se guardaron cambios.");
                        }

                        $dataRows++;
                        $key = Str::lower($name);
                        if (isset($seen[$key])) {
                            $skipped++;

                            continue;
                        }
                        $seen[$key] = true;
                        if ($existing->has($key)) {
                            if ($multiple !== null && $existing->get($key)->allows_multiple_products !== $multiple) {
                                $existing->get($key)->update(['allows_multiple_products' => $multiple]);
                                $updated++;
                            } else {
                                $skipped++;
                            }

                            continue;
                        }

                        $batch[] = ['name' => $name, 'allows_multiple_products' => $multiple ?? false, 'created_at' => now(), 'updated_at' => now()];
                        $imported++;

                        if (count($batch) === 500) {
                            foreach ($batch as $attributes) {
                                Category::create($attributes);
                            }
                            $batch = [];
                        }
                    }

                    break;
                }

                if (! $headerRead || $dataRows === 0) {
                    throw new InvalidArgumentException('El archivo no contiene categorías. Completa la columna Nombre debajo del encabezado.');
                }

                if ($batch !== []) {
                    foreach ($batch as $attributes) {
                        Category::create($attributes);
                    }
                }

                return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
            }, 'import');
        } finally {
            $reader->close();
        }
    }

    private function readName(Row $row, int $number, int $columnCount): string
    {
        foreach ($row->cells as $index => $cell) {
            if ($index >= $columnCount && $cell->getValue() !== null && $cell->getValue() !== '') {
                throw new InvalidArgumentException("Fila {$number}: el archivo contiene columnas no reconocidas. Descarga la plantilla de categorías.");
            }
        }

        $cell = $row->cells[0] ?? null;
        $value = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell?->getValue();
        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Fila {$number}: el nombre debe ser un texto. No se guardaron cambios.");
        }

        return trim((string) $value);
    }

    private function readMultiple(mixed $value, int $number): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            $text = Str::lower(trim((string) $value));
            if (in_array($text, ['sí', 'si', 'true', '1'], true)) {
                return true;
            }
            if (in_array($text, ['no', 'false', '0'], true)) {
                return false;
            }
            if ($text === '') {
                return null;
            }
        }
        throw new InvalidArgumentException("Fila {$number}: Es para múltiples productos debe ser Sí o No (también se acepta 1 o 0).");
    }
}
