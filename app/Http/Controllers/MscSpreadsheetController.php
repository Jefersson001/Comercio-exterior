<?php

namespace App\Http\Controllers;

use App\Models\MscShipment;
use App\Support\MscFields;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
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
        $relations = $foreignTrade
            ? ['product', 'category', 'factoryRecord', 'vessel']
            : ['product', 'category', 'factoryRecord', 'vessel', 'containers', 'productVariantLines.productVariant'];
        $shipments = MscShipment::query()->with($relations)
            ->search(trim((string) $request->input('search', '')), $foreignTrade)->orderBy('id')->lazy(500);

        if (! $foreignTrade) {
            return $this->downloadInternalReport($shipments, 'reporte-control-carga-interno-'.now()->format('Y-m-d').'.xlsx');
        }

        return $this->download($shipments, 'msc-'.now()->format('Y-m-d').'.xlsx', $foreignTrade);
    }

    public function exportDirect(Request $request): BinaryFileResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $shipments = MscShipment::query()
            ->with(['product', 'factoryRecord'])
            ->search(trim((string) $request->input('search', '')), true)
            ->orderBy('id')
            ->lazy(500);

        return $this->downloadDirectReport($shipments, 'reporte-control-carga-direct-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<MscShipment> $shipments */
    private function downloadInternalReport(iterable $shipments, string $filename): BinaryFileResponse
    {
        $headers = [
            'N°EXP.', 'DUA/INTR', 'SEABRIDGE', 'SENCAMER', 'COMEX', 'ESTATUS BL', 'STATUS',
            'BL Master', 'BL House', 'CONTENEDOR', 'CANT.', 'TIPO', 'MODELO/CONDICION',
            'PROVEEDOR', 'UNID TOTALES', 'BUQUE', 'SERVICIO FLETE', 'AÑO', 'COLORES',
            'ETD', 'ETA VZLA', 'PUERTO', 'TRASEGADO', 'IN BOND', 'DIAS LIBRES', 'DEMORA',
            'ABANDONO LEGAL', 'REGISTRO', 'RECONOCIMIENTO', 'VALIDACION', 'RECEPCIÓN PLANTA',
            'UBICACIÓN', 'DEVOLUCION', 'FECHA RECEP DE DOC ORIG', 'INSTRUCCIONES ENVIADAS AA',
            'FECHA LIBERACION BL', 'FECHA DE SOLICITUD DE SENCAMER', 'FECHA RECEPCION DE SENCAMER',
            'FECHA SOLICITUD DE COMEX', 'FECHA RECEPCION DE COMEX', 'STATUS AGENTE DE ADUANA',
            'OBSERVACION AGENTE DE ADUANA',
        ];
        $headerStyles = [];
        foreach ([[0, 26, '203764'], [27, 32, '008000'], [33, 39, '00B0E0'], [40, 41, '404040']] as [$start, $end, $backgroundColor]) {
            $style = new Style(fontBold: true, fontSize: 8, fontColor: Color::WHITE, backgroundColor: $backgroundColor, shouldWrapText: true);
            for ($index = $start; $index <= $end; $index++) {
                $headerStyles[$index] = $style;
            }
        }

        $reportCellStyle = new Style(fontSize: 8);
        $path = tempnam(sys_get_temp_dir(), 'msc-report-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga MSC.');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            try {
                $writer->addRow(Row::fromValuesWithStyles($headers, $headerStyles));
                foreach ($shipments as $shipment) {
                    $containers = $shipment->containers->pluck('name')->implode("\n");
                    $colors = $shipment->productVariantLines
                        ->map(fn ($line): string => ($line->quantity > 0 ? $line->quantity.' ' : '').$line->productVariant->color)
                        ->implode(' // ');
                    $date = fn (?\DateTimeInterface $value): string => $value?->format('Y-m-d') ?? '';
                    $values = [
                        $shipment->exp_number ?? '',
                        $shipment->dua_intr ?? '',
                        $shipment->seabridge ?? '',
                        $shipment->sencamer ?? '',
                        $shipment->comex ?? '',
                        $shipment->status_bl?->value ?? '',
                        $shipment->status?->value ?? '',
                        $shipment->bl_master ?? '',
                        $shipment->bl_house ?? '',
                        $containers,
                        $shipment->container_qty,
                        $shipment->category?->name ?? '',
                        $shipment->product->name,
                        $shipment->factoryRecord?->name ?? '',
                        $shipment->totalUnits() ?? '',
                        $shipment->vessel?->name ?? '',
                        $shipment->released ?? '',
                        $shipment->shipment_year ?? '',
                        $colors,
                        $date($shipment->etd),
                        $date($shipment->eta_vnzl),
                        $shipment->port ?? '',
                        $shipment->trasegado ?? '',
                        $date($shipment->in_bond_date),
                        $shipment->free_days ?? '',
                        $date($shipment->delay_date),
                        $date($shipment->legal_abandonment_date),
                        $date($shipment->registration_date),
                        $date($shipment->phase_two_recognition_date),
                        $date($shipment->validation_date),
                        $date($shipment->plant_receipt_date),
                        $shipment->location ?? '',
                        $date($shipment->return_date),
                        $date($shipment->original_docs_receipt_date),
                        $date($shipment->aa_instructions_sent_date),
                        $date($shipment->bl_release_date),
                        $date($shipment->sencamer_request_date),
                        $date($shipment->sencamer_receipt_date),
                        $date($shipment->comex_request_date),
                        $date($shipment->comex_receipt_date),
                        $shipment->customs_agent_status ?? '',
                        $shipment->customs_agent_observation ?? '',
                    ];
                    $cells = array_map(
                        static fn (string|int $value, int $index): NumericCell|StringCell => (in_array($index, [10, 14], true)
                            ? new NumericCell((int) $value)
                            : new StringCell((string) $value))->withStyle($reportCellStyle),
                        $values,
                        array_keys($values),
                    );
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

    /** @param iterable<MscShipment> $shipments */
    private function downloadDirectReport(iterable $shipments, string $filename): BinaryFileResponse
    {
        $headers = [
            'ESTATUS', 'BL Master', 'BL House', 'CANT.', 'MODELO/CONDICION',
            'PROVEEDOR', 'UNID TOTALES', 'NOTA', 'ETD', 'ETA VZLA', 'PUERTO',
        ];
        $headerStyle = new Style(
            fontBold: true,
            fontSize: 8,
            fontColor: Color::WHITE,
            backgroundColor: '203764',
            shouldWrapText: true,
        );
        $headerStyles = array_fill(0, count($headers), $headerStyle);
        $reportCellStyle = new Style(fontSize: 8, shouldWrapText: true);
        $path = tempnam(sys_get_temp_dir(), 'msc-direct-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga MSC.');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            try {
                $writer->addRow(Row::fromValuesWithStyles($headers, $headerStyles));
                foreach ($shipments as $shipment) {
                    $notes = array_filter([
                        $shipment->customs_agent_status ? 'STATUS AGENTE: '.$shipment->customs_agent_status : null,
                        $shipment->customs_agent_observation ? 'OBSERVACIÓN: '.$shipment->customs_agent_observation : null,
                    ]);
                    $values = [
                        $shipment->status?->value ?? '',
                        $shipment->bl_master ?? '',
                        $shipment->bl_house ?? '',
                        $shipment->container_qty,
                        $shipment->product->name,
                        $shipment->factoryRecord?->name ?? '',
                        $shipment->totalUnits() ?? '',
                        implode("\n", $notes),
                        $shipment->etd->format('Y-m-d'),
                        $shipment->eta_vnzl?->format('Y-m-d') ?? '',
                        $shipment->port ?? '',
                    ];
                    $cells = array_map(
                        static fn (string|int $value, int $index): NumericCell|StringCell => (in_array($index, [3, 6], true)
                            ? new NumericCell((int) $value)
                            : new StringCell((string) $value))->withStyle($reportCellStyle),
                        $values,
                        array_keys($values),
                    );
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
