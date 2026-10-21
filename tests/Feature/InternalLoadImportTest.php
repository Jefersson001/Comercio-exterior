<?php

namespace Tests\Feature;

use App\Actions\Msc\ImportInternalLoadReport;
use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\RecordChange;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class InternalLoadImportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        gc_collect_cycles();
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function row(array $overrides = []): array
    {
        return array_replace(array_fill(0, 46, ''), [
            0 => 238, 5 => 'LIBERADO EN ORIGEN', 8 => 'TRANSITO', 9 => 'NAVEGANDO',
            10 => "MASTER001\nHOUSE001", 13 => 'ABCU1234567ABCU1234567 XYZU7654321',
            14 => 2, 15 => 'ALL TERRAIN', 16 => 'Modelo A// Modelo B', 17 => 'Fábrica Uno',
            18 => 18, 19 => 'Buque Uno', 20 => 'MSC', 22 => 'LLV2C3B27T0225911',
            23 => '2026-10-01', 24 => '2026-10-20', 28 => 21, 40 => 'NO REQUIERE', 45 => 'Nota existente',
        ], $overrides);
    }

    private function file(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'internal-import-');
        $this->files[] = $path;
        $headers = array_replace(array_fill(0, 46, ''), [
            0 => 'N°EXP.', 5 => 'ESTATUS BL', 9 => 'STATUS', 10 => 'BL', 13 => 'CONTENEDOR',
            14 => 'CANT.', 15 => 'TIPO', 16 => 'MODELO/CONDICION', 17 => 'PROVEEDOR',
            18 => 'UNID TOTALES', 19 => 'BUQUE', 22 => 'COLORES', 23 => 'ETD',
            24 => 'ETA VZLA', 45 => 'OBSERVACION AGENTE DE ADUANA',
        ]);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(ImportInternalLoadReport::SHEET);
        $writer->addRow(Row::fromValues(['REPORTE']));
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    public function test_catalogs_combined_products_bl_and_notes_are_preserved_without_duplicates(): void
    {
        $path = $this->file([
            $this->row(),
            $this->row([0 => 239, 10 => 'MASTER002 HOUSE002', 15 => ' all  terrain ', 17 => ' FÁBRICA UNO ', 18 => 0, 19 => '']),
            array_replace(array_fill(0, 46, ''), [16 => 'TOTAL CARGAS', 18 => 99999]),
        ]);
        $action = app(ImportInternalLoadReport::class);
        $first = $action->handle($path, dryRun: false);
        $this->assertSame(2, $first['rows']);
        foreach (['categories', 'products', 'factories', 'vessels'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseCount('msc_shipments', 2);
        $this->assertDatabaseCount('msc_shipment_products', 2);
        $this->assertSame('Modelo A// Modelo B', Product::sole()->name);
        $this->assertNull(Product::sole()->units_per_container);
        $shipment = MscShipment::where('bl_master', 'MASTER001')->sole();
        $this->assertSame('HOUSE001', $shipment->bl_house);
        $this->assertSame(MscBillOfLadingStatus::ReleasedAtOrigin, $shipment->status_bl);
        $this->assertSame(MscShipmentStatus::Navegando, $shipment->status);
        $this->assertSame(18, $shipment->totalUnits());
        $this->assertCount(2, $shipment->containers);
        $this->assertStringContainsString('LLV2C3B27T0225911', $shipment->customs_agent_observation);
        $this->assertStringContainsString('Nota existente', $shipment->customs_agent_observation);
        $this->assertStringContainsString('NO REQUIERE', $shipment->customs_agent_observation);
        $this->assertNull($shipment->sencamer_request_date);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertNull(MscShipment::where('bl_master', 'MASTER002')->sole()->totalUnits());
        $this->assertSame(['import'], RecordChange::distinct()->pluck('source')->all());
        $history = RecordChange::count();
        $ids = $shipment->containers->pluck('id')->all();
        $second = $action->handle($path, dryRun: false);
        $this->assertSame(2, $second['unchanged']);
        $this->assertSame(0, array_sum($second['created']));
        $this->assertSame(0, $second['updated']);
        $this->assertSame($history, RecordChange::count());
        $this->assertSame($ids, $shipment->fresh()->containers->pluck('id')->all());
    }

    public function test_simulation_and_invalid_late_rows_leave_no_records_or_history(): void
    {
        $action = app(ImportInternalLoadReport::class);
        $result = $action->handle($this->file([$this->row()]));
        $this->assertSame(1, $result['created']['msc']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('record_changes', 0);
        try {
            $action->handle($this->file([$this->row(), $this->row([0 => 999, 10 => 'OTHER HOUSE', 9 => 'ESTADO DESCONOCIDO'])]), dryRun: false);
            $this->fail('Se esperaba un rechazo por status desconocido.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Fila 4', $exception->getMessage());
        }
        foreach ([Category::class, Product::class, Factory::class, Vessel::class, MscShipment::class, RecordChange::class] as $class) {
            $this->assertSame(0, $class::count());
        }
    }

    public function test_formula_cached_totals_and_receipt_status_are_loaded(): void
    {
        $row = $this->row([9 => 'RECEPCION 20-10-2026']);
        $path = $this->file([$row]);
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = preg_replace('/<c r="S3"[^>]*>.*?<\/c>/s', '<c r="S3"><f>25*4</f><v>100</v></c>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        app(ImportInternalLoadReport::class)->handle($path, dryRun: false);
        $shipment = MscShipment::sole();
        $this->assertSame(100, $shipment->totalUnits());
        $this->assertSame(MscShipmentStatus::Recepcion, $shipment->status);
        $this->assertSame('2026-10-20', $shipment->receipt_date->format('Y-m-d'));
    }

    public function test_duplicate_bl_is_rejected_but_duplicate_expedition_is_not_merged(): void
    {
        $action = app(ImportInternalLoadReport::class);
        try {
            $action->handle($this->file([$this->row(), $this->row([10 => ' master001   house001 '])]), dryRun: false);
            $this->fail('Se esperaba un rechazo de BL duplicado.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('BL repetido', $exception->getMessage());
        }
        $this->assertDatabaseCount('msc_shipments', 0);
        $action->handle($this->file([$this->row(), $this->row([10 => 'MASTER003 HOUSE003'])]), dryRun: false);
        $this->assertSame(2, MscShipment::where('exp_number', '238')->count());
    }
}
