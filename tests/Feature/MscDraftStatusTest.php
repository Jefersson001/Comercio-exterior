<?php

namespace Tests\Feature;

use App\Actions\Msc\ImportMscShipments;
use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Livewire\Pages\Msc\Form;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Support\MscFields;
use App\Support\MscReportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MscDraftStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_shipments_and_null_statuses_are_saved_as_draft(): void
    {
        foreach ([[], ['status' => null, 'status_bl' => null]] as $attributes) {
            $shipment = MscShipment::factory()->create($attributes)->fresh();
            $this->assertSame(MscShipmentStatus::Draft, $shipment->status);
            $this->assertSame(MscBillOfLadingStatus::Draft, $shipment->status_bl);
        }
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();
        Livewire::test(Form::class)->assertSet('status', 'Borrador')->assertSet('status_bl', 'Borrador')
            ->set('etd', '2026-10-01')->set('eta', '2026-11-01')->set('bl_master', 'NEW-DRAFT')
            ->set('product_id', $product->id)->set('container_qty', '1')->set('free_time', '0')
            ->set('status', '')->call('save')->assertHasNoErrors();
        $shipment = MscShipment::where('bl_master', 'NEW-DRAFT')->firstOrFail();
        $this->assertSame(MscShipmentStatus::Draft, $shipment->status);
        $this->assertSame(MscBillOfLadingStatus::Draft, $shipment->status_bl);
    }

    public function test_clearing_both_statuses_in_exterior_uses_draft_and_international_preserves_assigned_bl(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $shipment = MscShipment::factory()->create([
            'category_id' => $category->id, 'product_id' => $product->id,
            'status' => MscShipmentStatus::Puerto, 'status_bl' => MscBillOfLadingStatus::Released,
        ]);
        Livewire::test(Form::class, ['shipment' => $shipment])->set('status', '')->call('save')->assertHasNoErrors();
        $this->assertSame(MscShipmentStatus::Draft, $shipment->fresh()->status);
        $this->assertSame(MscBillOfLadingStatus::Released, $shipment->fresh()->status_bl);
        Livewire::test(Form::class, ['shipment' => $shipment->fresh(), 'foreignTrade' => true])
            ->set('status', '')->set('status_bl', '')->call('save')->assertHasNoErrors();
        $this->assertSame(MscShipmentStatus::Draft, $shipment->fresh()->status);
        $this->assertSame(MscBillOfLadingStatus::Draft, $shipment->fresh()->status_bl);
    }

    public function test_migration_backfills_only_empty_states_and_preserves_related_records(): void
    {
        $blank = MscShipment::factory()->create();
        $assigned = MscShipment::factory()->create(['status' => MscShipmentStatus::Puerto, 'status_bl' => MscBillOfLadingStatus::Released]);
        $blank->syncSingleProductLine();
        DB::table('msc_shipments')->where('id', $blank->id)->update(['status' => null, 'status_bl' => '  ']);
        $migration = require database_path('migrations/2026_10_21_140000_default_empty_msc_statuses_to_draft.php');
        $migration->up();
        $this->assertSame(MscShipmentStatus::Draft, $blank->fresh()->status);
        $this->assertSame(MscBillOfLadingStatus::Draft, $blank->fresh()->status_bl);
        $this->assertSame(1, $blank->productLines()->count());
        $this->assertSame(MscShipmentStatus::Puerto, $assigned->fresh()->status);
        $this->assertSame(MscBillOfLadingStatus::Released, $assigned->fresh()->status_bl);
    }

    public function test_imports_accept_draft_for_both_states_and_default_blank_states(): void
    {
        $product = Product::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'msc-draft-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(MscFields::HEADERS)));
        foreach (['BL-BLANK' => '', 'BL-DRAFT' => 'Borrador'] as $master => $status) {
            $writer->addRow(Row::fromValues([1, '2026-10-01', '2026-11-01', $master, '', '', $product->name, 1, '', 0, '', '', $status]));
        }
        $writer->addNewSheetAndMakeItCurrent();
        $writer->addRow(Row::fromValues(MscReportFields::INTERNAL_HEADERS));
        $row = array_fill(0, count(MscReportFields::INTERNAL_HEADERS), '');
        $row[5] = $row[6] = 'Borrador';
        $row[7] = 'BL-DRAFT';
        $writer->addRow(Row::fromValues($row));
        $writer->close();
        try {
            $result = app(ImportMscShipments::class)->handle($path, foreignTrade: true);
            $this->assertSame(2, $result['imported']);
            foreach (MscShipment::all() as $shipment) {
                $this->assertSame(MscShipmentStatus::Draft, $shipment->status);
                $this->assertSame(MscBillOfLadingStatus::Draft, $shipment->status_bl);
            }
        } finally {
            gc_collect_cycles();
            unlink($path);
        }
    }

    public function test_draft_filters_and_reports_identify_unfinished_shipments(): void
    {
        $this->actingAs(User::factory()->create());
        $draft = MscShipment::factory()->create();
        $assigned = MscShipment::factory()->create(['status' => MscShipmentStatus::Puerto, 'status_bl' => MscBillOfLadingStatus::Released]);
        foreach (['msc.' => ['status' => 'Borrador'], 'foreign-trade.msc.' => ['status_bl' => 'Borrador']] as $prefix => $filters) {
            $this->get(route($prefix.'index', $filters))->assertOk()->assertSee($draft->bl_master)->assertDontSee($assigned->bl_master);
            $response = $this->get(route($prefix.'export', $filters))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $reader = new Reader;
            $values = [];
            try {
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        array_push($values, ...$row->toArray());
                    }
                    break;
                }
            } finally {
                $reader->close();
                unlink($path);
            }
            $this->assertContains('Borrador', $values);
            $this->assertContains($draft->bl_master, $values);
            $this->assertNotContains($assigned->bl_master, $values);
        }
    }
}
