<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
            ->orderBy('name')->orderBy('id')->cursor()
            ->map(fn (Category $category): string => $category->name);

        return $this->download($names, 'categorías-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<string> $names */
    private function download(iterable $names, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'categorías-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga.');
        $writer = new Writer;

        try {
            $writer->openToFile($path);
            try {
                $writer->addRow(new Row([new StringCell('Nombre')]));
                foreach ($names as $name) {
                    $writer->addRow(new Row([new StringCell($name)]));
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
