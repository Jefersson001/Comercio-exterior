<?php

namespace App\Http\Controllers;

use App\Models\Factory;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FactorySpreadsheetController extends Controller
{
    public function template(): BinaryFileResponse
    {
        return $this->download([], 'plantilla-fabricas.xlsx');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->input('search', ''));
        $names = Factory::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->cursor()
            ->map(fn (Factory $factory): string => $factory->name);

        return $this->download($names, 'fabricas-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<string> $names */
    private function download(iterable $names, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fabricas-');
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
