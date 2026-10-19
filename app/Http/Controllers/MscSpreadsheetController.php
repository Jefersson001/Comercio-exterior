<?php

namespace App\Http\Controllers;

use App\Models\MscShipment;
use App\Support\MscFields;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MscSpreadsheetController extends Controller
{
    public function template(Request $request): BinaryFileResponse
    {
        return $this->download([], 'plantilla-msc.xlsx', $request->routeIs('foreign-trade.msc.*'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $foreignTrade = $request->routeIs('foreign-trade.msc.*');
        $shipments = MscShipment::query()->with($foreignTrade ? ['product', 'category', 'factoryRecord', 'vessel'] : ['product', 'factoryRecord', 'vessel'])
            ->search(trim((string) $request->input('search', '')), $foreignTrade)->orderBy('id')->lazy(500);

        return $this->download($shipments, 'msc-'.now()->format('Y-m-d').'.xlsx', $foreignTrade);
    }

    /** @param iterable<MscShipment> $shipments */
    private function download(iterable $shipments, string $filename, bool $foreignTrade): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'msc-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga MSC.');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            try {
                $writer->addRow(Row::fromValues(array_values(MscFields::headers($foreignTrade))));
                foreach ($shipments as $shipment) {
                    $cells = [
                        new NumericCell($shipment->id), new StringCell($shipment->etd->format('Y-m-d')),
                        new StringCell($shipment->eta->format('Y-m-d')), new StringCell($shipment->bl_master),
                        new StringCell($shipment->bl_house), new StringCell($shipment->factoryRecord?->name ?? ''), new StringCell($shipment->product->name),
                        new NumericCell($shipment->container_qty),
                        $shipment->totalUnits() === null ? new StringCell('') : new NumericCell($shipment->totalUnits()),
                        new NumericCell($shipment->free_time),
                        new StringCell($shipment->vessel?->name ?? ''), new StringCell($shipment->released ?? ''),
                        new StringCell($shipment->status?->value ?? ''),
                    ];
                    if ($foreignTrade) {
                        $cells[] = new StringCell($shipment->category->name ?? '');
                    }
                    $writer->addRow(new Row($cells));
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

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
