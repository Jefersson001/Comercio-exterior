<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductVariantSpreadsheet;
use App\Support\SpreadsheetLayout;
use App\Support\SpreadsheetTemplateLists;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductSpreadsheetController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return $this->download([], 'plantilla-productos.xlsx', true);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->input('search', ''));
        $products = Product::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->with(['category', 'variants'])->orderBy('name')->orderBy('id')->lazy(500);

        return $this->download($products, 'productos-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<Product> $products */
    private function download(iterable $products, string $filename, bool $template = false): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'productos-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga.');
        $options = new Options;
        $lists = $template ? new SpreadsheetTemplateLists($options) : null;
        if ($lists !== null) {
            $lists->dropdown(0, 0, $lists->formula($lists->source('Productos existentes', Product::orderBy('name')->pluck('name')->all())), 'producto', false);
            $lists->dropdown(0, 2, $lists->formula($lists->source('Categorías', Category::orderBy('name')->pluck('name')->all())), 'categoría');
            $lists->number(0, 1);
            $colors = Product::with(['variants', 'category'])->get()->reject(fn (Product $product): bool => $product->category?->allows_multiple_products ?? false)->flatMap(fn (Product $product) => $product->variants->pluck('color'))->unique()->sort()->values()->all();
            $lists->dropdown(0, 3, $lists->formula($lists->source('Colores existentes', $colors)), 'color', false);
        }
        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            try {
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Productos', 4, [1 => 40, 3 => 40, 4 => 50]);
                $writer->addRow(Row::fromValuesWithStyle(['Nombre', 'Unidades por contenedor', 'Categoría', 'Variantes por color'], SpreadsheetLayout::header()));
                foreach ($products as $product) {
                    $writer->addRow(new Row([
                        new StringCell($product->name),
                        $product->units_per_container === null
                            ? new StringCell('')
                            : new NumericCell($product->units_per_container),
                        new StringCell($product->category?->name ?? ''),
                        (new StringCell($product->category?->allows_multiple_products ? '' : ProductVariantSpreadsheet::format($product->variants->pluck('color')->all())))->withStyle(SpreadsheetLayout::body()),
                    ]));
                }
                $lists?->write($writer);
            } finally {
                $writer->close();
            }
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }

        clearstatcache(true, $path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
