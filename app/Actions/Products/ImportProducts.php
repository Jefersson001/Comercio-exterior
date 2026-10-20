<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductVariantSpreadsheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class ImportProducts
{
    /** @return array{imported: int, updated: int, skipped: int} */
    public function handle(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        try {
            return DB::transaction(function () use ($reader): array {
                $existing = [];
                foreach (Product::query()->get(['id', 'name']) as $product) {
                    $key = $this->normalize($product->name);
                    if (array_key_exists($key, $existing)) {
                        throw new InvalidArgumentException('Hay productos con nombres equivalentes. Corrige el catálogo antes de importar unidades.');
                    }
                    $existing[$key] = $product->id;
                }
                $header = null;
                $seen = [];
                $dataRows = 0;
                $imported = 0;
                $updated = 0;
                $skipped = 0;
                $batch = [];
                $categories = [];
                foreach (Category::query()->get(['id', 'name']) as $category) {
                    $key = $this->normalize($category->name);
                    $categories[$key] = array_key_exists($key, $categories) ? null : $category->id;
                }

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        if ($header === null) {
                            $header = $this->readHeader($row);

                            continue;
                        }

                        $values = $this->readProductRow($row, $number, count($header));
                        $name = Str::squish((string) ($values[0] ?? ''));
                        $quantityValue = $header[1] ?? null;
                        $unitsPerContainer = $quantityValue === null
                            ? null
                            : $this->readUnits($values[$quantityValue] ?? null, $number);
                        $categoryName = $header[2] ?? null;
                        $categoryValue = $categoryName === null ? null : ($values[$categoryName] ?? null);
                        if ($categoryValue !== null && ! is_string($categoryValue) && ! is_int($categoryValue) && ! is_float($categoryValue)) {
                            throw new InvalidArgumentException("Fila {$number}: la categoría debe ser un texto.");
                        }
                        $categoryText = Str::squish((string) $categoryValue);
                        $categoryId = $categoryText === '' ? null : ($categories[$this->normalize($categoryText)] ?? null);
                        if ($categoryText !== '' && $categoryId === null) {
                            throw new InvalidArgumentException("Fila {$number}: la categoría «{$categoryText}» no existe o no es única. Cárgala primero en Categorías.");
                        }
                        $colors = isset($header[3]) ? ProductVariantSpreadsheet::parse($values[$header[3]] ?? null, $number) : [];

                        if ($name === '' && $unitsPerContainer === null && $categoryText === '' && $colors === []) {
                            $skipped++;

                            continue;
                        }
                        if ($name === '') {
                            throw new InvalidArgumentException("Fila {$number}: ingresa el nombre del producto. No se importó ningún producto.");
                        }
                        if (Str::length($name) > 255) {
                            throw new InvalidArgumentException("Fila {$number}: el nombre no puede superar los 255 caracteres. No se importó ningún producto.");
                        }

                        $dataRows++;
                        $key = $this->normalize($name);
                        if (isset($seen[$key])) {
                            $skipped++;

                            continue;
                        }
                        $seen[$key] = true;
                        $product = isset($existing[$key]) ? Product::findOrFail($existing[$key]) : null;
                        $effectiveCategoryId = $categoryId ?? $product?->category_id;
                        if ($colors !== [] && Category::find($effectiveCategoryId)?->allows_multiple_products) {
                            throw new InvalidArgumentException("Fila {$number}: las categorías de múltiples productos no permiten variantes. Deja Variantes por color vacío.");
                        }

                        if ($product !== null) {
                            $changes = [];
                            if ($unitsPerContainer !== null) {
                                $changes['units_per_container'] = $unitsPerContainer;
                            }
                            if ($categoryId !== null) {
                                $changes['category_id'] = $categoryId;
                            }
                            $variantsAdded = $this->addVariants($product, $colors);
                            if ($changes !== [] || $variantsAdded > 0) {
                                if ($changes !== []) {
                                    Product::query()->whereKey($existing[$key])->update($changes);
                                }
                                $updated++;
                            } else {
                                $skipped++;
                            }

                            continue;
                        }

                        if ($colors !== []) {
                            $product = Product::create(['name' => $name, 'units_per_container' => $unitsPerContainer, 'category_id' => $categoryId]);
                            $existing[$key] = $product->id;
                            $this->addVariants($product, $colors);
                            $imported++;

                            continue;
                        }

                        $existing[$key] = true;
                        $batch[] = [
                            'name' => $name,
                            'units_per_container' => $unitsPerContainer,
                            'category_id' => $categoryId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $imported++;

                        if (count($batch) === 500) {
                            Product::query()->insert($batch);
                            $batch = [];
                        }
                    }

                    break;
                }

                if ($header === null || $dataRows === 0) {
                    throw new InvalidArgumentException('El archivo no contiene productos. Completa la columna Nombre debajo del encabezado.');
                }

                if ($batch !== []) {
                    Product::query()->insert($batch);
                }

                return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
            });
        } finally {
            $reader->close();
        }
    }

    /** @return array<int, int> */
    private function readHeader(Row $row): array
    {
        $headers = [];
        foreach ($row->cells as $index => $cell) {
            $value = $cell instanceof TextRunCell ? $cell->getStringValue() : $cell->getValue();
            $headers[$index] = Str::lower(Str::squish((string) $value));
        }

        $hasUnitsHeader = in_array($headers[1] ?? null, ['unidades por contenedor', 'units per container'], true);
        $hasCategoryHeader = in_array($headers[2] ?? null, ['categoría', 'categoria', 'category'], true);
        $hasVariantsHeader = in_array($headers[3] ?? null, ['variantes por color', 'variantes', 'variants', 'colors'], true);
        foreach ($headers as $index => $header) {
            if (($index === 0 && ! in_array($header, ['nombre', 'name'], true))
                || ($index === 1 && ! $hasUnitsHeader)
                || ($index === 2 && ! $hasCategoryHeader)
                || ($index === 3 && ! $hasVariantsHeader)
                || $index > 3) {
                throw new InvalidArgumentException('Las columnas deben ser Nombre, Unidades por contenedor, Categoría y Variantes por color. También se aceptan archivos antiguos con las primeras una, dos o tres columnas. Descarga la plantilla para usar el formato correcto.');
            }
        }

        if ($headers === []) {
            throw new InvalidArgumentException('El archivo no contiene encabezados. Descarga la plantilla para usar el formato correcto.');
        }

        return $hasVariantsHeader ? [0 => 0, 1 => 1, 2 => 2, 3 => 3] : ($hasCategoryHeader ? [0 => 0, 1 => 1, 2 => 2] : ($hasUnitsHeader ? [0 => 0, 1 => 1] : [0 => 0]));
    }

    /** @param list<string> $colors */
    private function addVariants(Product $product, array $colors): int
    {
        if ($colors === []) {
            return 0;
        }
        $existing = $product->variants()->get()->mapWithKeys(fn (ProductVariant $variant): array => [Str::lower(trim($variant->color)) => true])->all();
        $added = 0;
        foreach ($colors as $color) {
            $key = Str::lower($color);
            if (! isset($existing[$key])) {
                $product->variants()->create(['color' => $color]);
                $existing[$key] = true;
                $added++;
            }
        }

        return $added;
    }

    /** @return array<int, mixed> */
    private function readProductRow(Row $row, int $number, int $columnCount): array
    {
        foreach ($row->cells as $index => $cell) {
            if ($index >= $columnCount && $cell->getValue() !== null && $cell->getValue() !== '') {
                throw new InvalidArgumentException("Fila {$number}: el archivo contiene columnas no reconocidas. No se importó ningún producto.");
            }
        }

        return array_map(
            fn ($cell): mixed => $cell instanceof TextRunCell ? $cell->getStringValue() : $cell?->getValue(),
            array_values($row->cells),
        );
    }

    private function readUnits(mixed $value, int $number): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if ((! is_int($value) && ! is_float($value) && ! is_string($value))
            || ! preg_match('/^[1-9]\d*$/', trim((string) $value))
            || (float) $value > 2147483647) {
            throw new InvalidArgumentException("Fila {$number}: las unidades por contenedor deben ser un entero entre 1 y 2147483647. No se importó ningún producto.");
        }

        return (int) $value;
    }

    private function normalize(string $name): string
    {
        return Str::lower(Str::squish($name));
    }
}
