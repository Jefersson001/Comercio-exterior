<?php

namespace App\Http\Controllers;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\Vessel;
use App\Support\MscFields;
use App\Support\MscReportFields;
use App\Support\SpreadsheetLayout;
use App\Support\SpreadsheetTemplateLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MscSpreadsheetController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'msc-template-');
        abort_if($path === false, 500, 'No se pudo preparar la plantilla MSC.');

        $options = new Options;
        $lists = $this->templateLists($options);
        $writer = new Writer($options);
        try {
            $writer->openToFile($path);
            try {
                $internationalSheet = $writer->getCurrentSheet();
                SpreadsheetLayout::configure($internationalSheet, 'Comercio Internacional', count(MscFields::HEADERS));
                $internationalHeaders = array_values(MscFields::HEADERS);
                $internationalStyle = new Style(
                    fontBold: true,
                    fontSize: 8,
                    fontColor: Color::WHITE,
                    backgroundColor: '203764',
                    shouldWrapText: true,
                );
                $writer->addRow(Row::fromValuesWithStyles(
                    $internationalHeaders,
                    array_fill(0, count($internationalHeaders), $internationalStyle),
                ));

                $foreignTradeSheet = $writer->addNewSheetAndMakeItCurrent();
                SpreadsheetLayout::configure($foreignTradeSheet, 'Comercio Exterior', count(MscReportFields::INTERNAL_HEADERS));
                $writer->addRow(Row::fromValuesWithStyles(
                    MscReportFields::INTERNAL_HEADERS,
                    $this->internalReportHeaderStyles(),
                ));
                $writer->addNewSheetAndMakeItCurrent();
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Instrucciones', 2, [2 => 100]);
                $writer->addRow(Row::fromValuesWithStyle(['Campo', 'Indicaciones'], SpreadsheetLayout::header()));
                foreach ([
                    ['Listas desplegables', 'Selecciona los catálogos existentes desde las listas de las celdas. En Comercio Exterior, selecciona TIPO antes del modelo para filtrar los productos por categoría. La hoja Listas es una referencia: no la edites.'],
                    ['Catálogos desactualizados', 'Si falta una opción, regístrala en Configuración y descarga una plantilla nueva. Pegar datos puede omitir las alertas de Excel; la importación volverá a validar cada fila.'],
                    ['Uso', 'Esta plantilla importa un producto por embarque. Los embarques múltiples se administran en Comercio Exterior → MSC. Los reportes son archivos de consulta; su hoja Detalle productos no es una plantilla de importación.'],
                    ['Identificación', 'Usa el mismo BL Master y BL House en ambas hojas. Para crear embarques, completa primero Comercio Internacional. No incluyas títulos ni filas de totales.'],
                    ['Cant Unidades', 'Si no se conocen las unidades, deja la celda vacía. Una celda vacía no significa cero.'],
                    ['Categoría / TIPO', 'La categoría debe existir en Configuración → Categorías. Los productos de Comercio Exterior deben tener asignada su categoría.'],
                    ['COLORES', 'Solo corresponde a categorías individuales. Usa variantes existentes del producto, por ejemplo: 12 rojo // 5 blanco. Déjala vacía en categorías múltiples.'],
                    ['Fechas', 'Usa el formato AAAA-MM-DD. ETA debe ser igual o posterior a ETD.'],
                    ['Estados vacíos', 'Si el embarque no tiene estado asignado, se guardará como Borrador. Lo mismo aplica al Status BL. Borrador indica que el seguimiento está pendiente de completar.'],
                    ['Status del embarque', implode(' | ', array_column(MscShipmentStatus::options(), 'value'))],
                    ['Status BL', implode(' | ', array_column(MscBillOfLadingStatus::cases(), 'value'))],
                ] as $instruction) {
                    $writer->addRow(Row::fromValuesWithStyle($instruction, SpreadsheetLayout::body()));
                }
                $lists->write($writer);
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

        return response()->download($path, 'plantilla-msc.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function templateLists(Options $options): SpreadsheetTemplateLists
    {
        $lists = new SpreadsheetTemplateLists($options);
        $categories = Category::orderBy('name')->get();
        $products = Product::with(['category', 'variants'])->orderBy('name')->get();
        $productSource = $lists->formula($lists->source('Productos', $products->pluck('name')->all()));
        $factorySource = $lists->formula($lists->source('Fábricas', Factory::orderBy('name')->pluck('name')->all()));
        $vesselSource = $lists->formula($lists->source('Buques', Vessel::orderBy('name')->pluck('name')->all()));
        $categorySource = $lists->formula($lists->source('Categorías', $categories->pluck('name')->all()));
        $statusSource = $lists->formula($lists->source('Status del embarque', array_column(MscShipmentStatus::options(), 'value')));
        $blSource = $lists->formula($lists->source('Status BL', array_column(MscBillOfLadingStatus::cases(), 'value')));
        $groups = [];
        foreach ($categories as $category) {
            $groups[$category->name] = $products->where('category_id', $category->id)->pluck('name')->values()->all();
        }
        $colors = [];
        foreach ($products as $product) {
            $colors[$product->name] = $product->category?->allows_multiple_products ? [] : $product->variants->pluck('color')->all();
        }
        foreach ([[0, 5, $factorySource, 'fábrica'], [0, 6, $productSource, 'producto'], [0, 10, $vesselSource, 'buque'], [0, 12, $statusSource, 'estado'], [1, 5, $blSource, 'Status BL'], [1, 6, $statusSource, 'estado'], [1, 11, $categorySource, 'categoría'], [1, 12, $lists->dependent('$L2', 'Productos por categoría', $groups), 'producto'], [1, 13, $factorySource, 'fábrica'], [1, 15, $vesselSource, 'buque']] as [$sheet, $column, $formula, $label]) {
            $lists->dropdown($sheet, $column, $formula, $label);
        }
        $lists->dropdown(1, 18, $lists->dependent('$M2', 'Colores por producto', $colors), 'color', false);
        foreach ([[0, 7, 1], [0, 8, 1], [0, 9, 0], [1, 10, 1], [1, 14, 1], [1, 24, 0]] as [$sheet, $column, $minimum]) {
            $lists->number($sheet, $column, $minimum);
        }

        return $lists;
    }

    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'foreign_trade' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::enum(MscShipmentStatus::class)],
            'status_bl' => ['nullable', Rule::enum(MscBillOfLadingStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'factory_id' => ['nullable', 'integer', 'exists:factories,id'],
        ]);
        $foreignTradeContext = $request->boolean('foreign_trade') || $request->routeIs('foreign-trade.msc.*');
        Gate::authorize($foreignTradeContext ? 'access-foreign-trade' : 'access-international-trade');
        $isForeignTradeRoute = $request->routeIs('foreign-trade.msc.*');
        $relations = $isForeignTradeRoute
            ? ['product', 'productLines.product', 'category', 'factoryRecord', 'vessel', 'containers', 'productVariantLines.productVariant']
            : ['product', 'productLines.product', 'factoryRecord', 'vessel'];
        $shipments = MscShipment::query()->with($relations)
            ->filters((string) $request->input('status'), $foreignTradeContext ? (string) $request->input('status_bl') : '', $foreignTradeContext ? (string) $request->input('category_id') : '', (string) $request->input('factory_id'))
            ->search(trim((string) $request->input('search', '')), $foreignTradeContext)->orderBy('id')->lazy(500);

        if ($isForeignTradeRoute) {
            return $this->downloadInternalReport($shipments, 'reporte-control-carga-interno-'.now()->format('Y-m-d').'.xlsx');
        }

        return $this->downloadInternationalReport($shipments, 'reporte-msc-comercio-internacional-'.now()->format('Y-m-d').'.xlsx');
    }

    public function exportDirect(Request $request): BinaryFileResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'foreign_trade' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::enum(MscShipmentStatus::class)],
            'status_bl' => ['nullable', Rule::enum(MscBillOfLadingStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'factory_id' => ['nullable', 'integer', 'exists:factories,id'],
        ]);
        $foreignTradeContext = $request->boolean('foreign_trade') || $request->routeIs('foreign-trade.msc.*');
        Gate::authorize($foreignTradeContext ? 'access-foreign-trade' : 'access-international-trade');
        $shipments = MscShipment::query()
            ->with(['product', 'productLines.product', 'factoryRecord'])
            ->filters((string) $request->input('status'), $foreignTradeContext ? (string) $request->input('status_bl') : '', $foreignTradeContext ? (string) $request->input('category_id') : '', (string) $request->input('factory_id'))
            ->search(trim((string) $request->input('search', '')), $foreignTradeContext)
            ->orderBy('id')
            ->lazy(500);

        return $this->downloadDirectReport($shipments, 'reporte-control-carga-direct-'.now()->format('Y-m-d').'.xlsx');
    }

    /** @param iterable<MscShipment> $shipments */
    private function downloadInternalReport(iterable $shipments, string $filename): BinaryFileResponse
    {
        $headers = MscReportFields::INTERNAL_HEADERS;
        $headerStyles = $this->internalReportHeaderStyles();

        $reportCellStyle = SpreadsheetLayout::body();
        $path = tempnam(sys_get_temp_dir(), 'msc-report-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga MSC.');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            try {
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Control carga interno', count($headers), [13 => 42, 41 => 42, 42 => 42]);
                $writer->addRow(Row::fromValuesWithStyles($headers, $headerStyles));
                foreach ($shipments as $shipment) {
                    $containers = $shipment->containers->pluck('name')->implode("\n");
                    $colors = $shipment->category?->allows_multiple_products ? '' : $shipment->productVariantLines
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
                        $shipment->totalContainers(),
                        $shipment->category?->name ?? '',
                        $shipment->productNames(),
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
                        static fn (string|int $value, int $index): NumericCell|StringCell => (in_array($index, [10, 14], true) && $value !== ''
                            ? new NumericCell((int) $value)
                            : new StringCell((string) $value))->withStyle($reportCellStyle),
                        $values,
                        array_keys($values),
                    );
                    $writer->addRow(new Row($cells));
                    $this->writeProductDetails($writer, $shipment);
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
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Control carga directo', count($headers), [5 => 42, 8 => 42]);
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
                        $shipment->totalContainers(),
                        $shipment->productNames(),
                        $shipment->factoryRecord?->name ?? '',
                        $shipment->totalUnits() ?? '',
                        implode("\n", $notes),
                        $shipment->etd->format('Y-m-d'),
                        $shipment->eta_vnzl?->format('Y-m-d') ?? '',
                        $shipment->port ?? '',
                    ];
                    $cells = array_map(
                        static fn (string|int $value, int $index): NumericCell|StringCell => (in_array($index, [3, 6], true) && $value !== ''
                            ? new NumericCell((int) $value)
                            : new StringCell((string) $value))->withStyle($reportCellStyle),
                        $values,
                        array_keys($values),
                    );
                    $writer->addRow(new Row($cells));
                    $this->writeProductDetails($writer, $shipment);
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
    private function downloadInternationalReport(iterable $shipments, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'msc-');
        abort_if($path === false, 500, 'No se pudo preparar la descarga MSC.');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            try {
                SpreadsheetLayout::configure($writer->getCurrentSheet(), 'Comercio Internacional', count(MscFields::HEADERS), [7 => 42]);
                $writer->addRow(Row::fromValuesWithStyle(array_values(MscFields::HEADERS), SpreadsheetLayout::header()));
                foreach ($shipments as $shipment) {
                    $cells = [
                        new NumericCell($shipment->id), new StringCell($shipment->etd->format('Y-m-d')),
                        new StringCell($shipment->eta->format('Y-m-d')), new StringCell($shipment->bl_master ?? ''),
                        new StringCell($shipment->bl_house ?? ''), new StringCell($shipment->factoryRecord?->name ?? ''), new StringCell($shipment->productNames()),
                        new NumericCell($shipment->totalContainers()),
                        $shipment->totalUnits() === null ? new StringCell('') : new NumericCell($shipment->totalUnits()),
                        new NumericCell($shipment->free_time),
                        new StringCell($shipment->vessel?->name ?? ''), new StringCell($shipment->released ?? ''),
                        new StringCell($shipment->status?->value ?? ''),
                    ];
                    $writer->addRow(new Row(array_map(fn ($cell) => $cell->withStyle(SpreadsheetLayout::body()), $cells)));
                    $this->writeProductDetails($writer, $shipment);
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

    private function writeProductDetails(Writer $writer, MscShipment $shipment): void
    {
        if ($shipment->productLines->count() < 2) {
            return;
        }
        $mainSheet = $writer->getCurrentSheet();
        $detailSheet = null;
        foreach ($writer->getSheets() as $sheet) {
            if ($sheet->getName() === 'Detalle productos') {
                $detailSheet = $sheet;
                break;
            }
        }
        try {
            if ($detailSheet === null) {
                $detailSheet = $writer->addNewSheetAndMakeItCurrent();
                SpreadsheetLayout::configure($detailSheet, 'Detalle productos', 6, [4 => 42]);
                $writer->addRow(Row::fromValuesWithStyle(['No.', 'BL Master', 'BL House', 'Model', 'Cant contenedores', 'Cant Unidades'], SpreadsheetLayout::header()));
            } else {
                $writer->setCurrentSheet($detailSheet);
            }
            foreach ($shipment->productLines as $line) {
                $style = SpreadsheetLayout::body();
                $writer->addRow(new Row([
                    (new NumericCell($shipment->id))->withStyle($style),
                    (new StringCell($shipment->bl_master ?? ''))->withStyle($style),
                    (new StringCell($shipment->bl_house ?? ''))->withStyle($style),
                    (new StringCell($line->product->name))->withStyle($style),
                    (new NumericCell($line->container_qty))->withStyle($style),
                    ($line->totalUnits() === null ? new StringCell('') : new NumericCell($line->totalUnits()))->withStyle($style),
                ]));
            }
        } finally {
            $writer->setCurrentSheet($mainSheet);
        }
    }

    /** @return array<int, Style> */
    private function internalReportHeaderStyles(): array
    {
        $styles = [];
        foreach ([[0, 26, '203764'], [27, 32, '008000'], [33, 39, '00B0E0'], [40, 41, '404040']] as [$start, $end, $backgroundColor]) {
            $style = new Style(fontBold: true, fontSize: 8, fontColor: Color::WHITE, backgroundColor: $backgroundColor, shouldWrapText: true);
            for ($index = $start; $index <= $end; $index++) {
                $styles[$index] = $style;
            }
        }

        return $styles;
    }
}
