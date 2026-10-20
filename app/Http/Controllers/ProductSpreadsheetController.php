<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductVariantSpreadsheet;
use App\Support\SpreadsheetLayout;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductSpreadsheetController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return $this->download([], 'plantilla-productos.xlsx');
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
    private function download(iterable $products, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'productos-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga.');
        $writer = new Writer;

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
