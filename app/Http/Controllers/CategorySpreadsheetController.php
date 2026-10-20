<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\SpreadsheetLayout;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CategorySpreadsheetController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return $this->download([], 'plantilla-categorías.xlsx');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->input('search', ''));
        $names = Category::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->cursor();

        return $this->download($names, 'categorías-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<Category> $names */
    private function download(iterable $names, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'categorías-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga.');
        $writer = new Writer;

        try {
            $writer->openToFile($path);
            try {
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Categorías', 2, [1 => 40, 2 => 40]);
                $writer->addRow(Row::fromValuesWithStyle(['Nombre', 'Es para múltiples productos'], SpreadsheetLayout::header()));
                foreach ($names as $category) {
                    $writer->addRow(new Row([new StringCell($category->name), new StringCell($category->allows_multiple_products ? 'Sí' : 'No')]));
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
