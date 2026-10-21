<?php

namespace Tests\Feature;

use App\Actions\Msc\ImportDispatchedReport;
use App\Enums\MscShipmentStatus;
use App\Livewire\Pages\Msc\Form;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\RecordChange;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class DispatchedLoadImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_deliveries_are_grouped_and_exact_duplicate_is_excluded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dispatched-test-');
        $headers = array_replace(array_fill(0, 42, ''), [8 => 'BL', 12 => 'CANT.', 13 => 'TIPO', 14 => 'MODELO', 15 => 'PROVEEDOR', 16 => 'UNID TOTALES', 21 => 'ETD', 22 => 'ETA VZLA']);
        $base = array_replace(array_fill(0, 42, ''), [0 => 127, 4 => 'BL LIBERADO OK', 7 => 'DESPACHADO 19/09/2026', 8 => "MASTER001\nHOUSE001", 12 => 2, 13 => 'MOTO', 14 => 'Modelo', 15 => 'Fábrica', 16 => 64, 17 => 'Buque', 22 => '2026-07-05', 26 => 21, 31 => '2026-09-19']);
        $second = array_replace($base, [7 => 'RECEPCION 23/09/2026', 12 => 3, 16 => 96, 31 => '2026-09-23']);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(ImportDispatchedReport::SHEET);
        foreach ([$headers, $base, $base, $second] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        try {
            $action = app(ImportDispatchedReport::class);
            $simulation = $action->handle($path);
            $this->assertDatabaseCount('msc_shipments', 0);
            $this->assertDatabaseCount('record_changes', 0);
            $this->assertSame(3, $simulation['source_rows']);
            $result = $action->handle($path, false);
            $this->assertSame([['row' => 3, 'duplicate_of' => 2]], $result['excluded_duplicates']);
            $shipment = MscShipment::sole();
            $this->assertSame(5, $shipment->totalContainers());
            $this->assertSame(160, $shipment->totalUnits());
            $this->assertNull($shipment->etd);
            $this->assertSame(MscShipmentStatus::Recepcion, $shipment->status);
            $this->assertSame('', $shipment->comex);
            $this->assertSame('2026-09-23', $shipment->plant_receipt_date->format('Y-m-d'));
            $this->assertStringContainsString('Entrega / fila 2', $shipment->customs_agent_observation);
            $this->assertStringContainsString('Entrega / fila 4', $shipment->customs_agent_observation);
            $this->assertStringContainsString('excluida por ser idéntica', $shipment->customs_agent_observation);
            $history = RecordChange::count();
            $repeat = $action->handle($path, false);
            $this->assertSame(1, $repeat['unchanged']);
            $this->assertSame(0, $repeat['updated']);
            $this->assertSame($history, RecordChange::count());
        } finally {
            gc_collect_cycles();
            unlink($path);
        }
    }

    public function test_unknown_etd_can_be_saved_and_rendered_while_known_dates_still_validate(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $product = Product::factory()->create();
        $form = Livewire::test(Form::class)->fill([
            'etd' => '', 'eta' => '2026-10-20', 'bl_master' => 'HISTORICAL001',
            'product_id' => $product->id, 'container_qty' => '2', 'free_time' => '21',
        ])->call('save')->assertHasNoErrors();
        $shipment = MscShipment::sole();
        $this->assertNull($shipment->etd);
        $this->get(route('msc.index'))->assertOk()->assertSee('Sin fecha');
        Livewire::test(Form::class, ['shipment' => $shipment])->assertSet('etd', '')
            ->set('etd', '2026-11-01')->call('save')->assertHasErrors('eta')
            ->set('etd', '')->call('save')->assertHasNoErrors()->assertNoRedirect();
        foreach (['msc.export', 'foreign-trade.msc.export', 'foreign-trade.msc.export-direct'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertDownload();
            unlink($response->baseResponse->getFile()->getPathname());
        }
    }
}
