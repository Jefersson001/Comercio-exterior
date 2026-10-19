<?php

namespace Tests\Feature;

use App\Actions\Msc\ImportMscShipments;
use App\Livewire\Pages\Msc\Import;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\Vessel;
use App\Models\User;
use App\Support\MscFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MscSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<list<mixed>> $rows */
    private function spreadsheet(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test-msc-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('msc.xlsx', $content);
    }

    private function row(string $blMaster = 'BL-NEW', string $model = 'Model One', string $blHouse = ''): array
    {
        return [999, '2026-10-01', '2026-11-01', $blMaster, $blHouse, 'Factory One', $model, 5, '', '21 days', 'Agent 1', ''];
    }

    private function upload(array $rows): UploadedFile
    {
        return $this->spreadsheet([array_values(MscFields::HEADERS), ...$rows]);
    }

    /** @return list<array<mixed>> */
    private function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_values($row->toArray());
                }
                break;
            }
        } finally {
            $reader->close();
            unset($reader, $sheet, $row);
            unlink($path);
        }

        return $rows;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Product::factory()->create(['name' => 'Model One']);
    }

    public function test_import_ignores_source_numbers_and_continues_the_system_sequence(): void
    {
        $existing = MscShipment::factory()->create();
        Livewire::test(Import::class)->set('file', $this->upload([$this->row()]))->call('import')
            ->assertHasNoErrors()->assertRedirect(route('msc.index'));
        $new = MscShipment::where('bl_master', 'BL-NEW')->firstOrFail();
        $this->assertSame($existing->id + 1, $new->id);
        $this->assertNotSame(999, $new->id);
        $this->assertSame(21, $new->free_time);
        $this->assertSame(5, $new->container_qty);
        $this->assertSame('2026-10-01', $new->etd->format('Y-m-d'));
        $this->assertSame('', $new->bl_house);
        $this->assertSame('Model One', $new->product->name);
    }

    public function test_import_links_only_factories_found_in_the_catalog(): void
    {
        $factory = Factory::factory()->create(['name' => 'Factory One']);
        $matchedRow = $this->row('BL-MATCHED');
        $unknownRow = $this->row('BL-UNKNOWN');
        $unknownRow[5] = 'Factory Not Catalogued';
        $rows = [$matchedRow, $unknownRow];
        $upload = $this->upload($rows);

        app(ImportMscShipments::class)->handle($upload->getPathname());

        $this->assertSame($factory->id, MscShipment::where('bl_master', 'BL-MATCHED')->firstOrFail()->factory_id);
        $this->assertNull(MscShipment::where('bl_master', 'BL-UNKNOWN')->firstOrFail()->factory_id);
    }

    public function test_import_links_only_buques_found_in_the_catalog_and_supports_the_legacy_header(): void
    {
        $vessel = Vessel::factory()->create(['name' => 'Buque Aurora']);
        $matched = $this->row('BL-VESSEL-MATCHED');
        $matched[10] = 'Buque Aurora';
        $unknown = $this->row('BL-VESSEL-UNKNOWN');
        $unknown[10] = 'Buque sin catálogo';

        $upload = $this->upload([$matched, $unknown]);
        app(ImportMscShipments::class)->handle($upload->getPathname());

        $this->assertSame($vessel->id, MscShipment::where('bl_master', 'BL-VESSEL-MATCHED')->firstOrFail()->vessel_id);
        $this->assertNull(MscShipment::where('bl_master', 'BL-VESSEL-UNKNOWN')->firstOrFail()->vessel_id);

        $legacyHeaders = ['No.', 'ETD', 'ETA', 'Bill of Lading NO.', 'Destination', 'Shipping line', 'Factory', 'Model', 'Container Qty', 'Free time', 'Ship Agent', 'RELEASED'];
        $legacyRow = [1, '2026-10-01', '2026-11-01', 'BL-LEGACY-VESSEL', '', '', 'Factory One', 'Model One', 5, 21, 'Buque Aurora', ''];
        $legacyUpload = $this->spreadsheet([$legacyHeaders, $legacyRow]);
        app(ImportMscShipments::class)->handle($legacyUpload->getPathname());
        $this->assertSame($vessel->id, MscShipment::where('bl_master', 'BL-LEGACY-VESSEL')->firstOrFail()->vessel_id);
    }

    public function test_import_accepts_a_manual_unit_total_and_export_contains_the_calculated_total(): void
    {
        $product = Product::firstOrFail();
        $product->update(['units_per_container' => 24]);

        $manualRow = $this->row('BL-UNIT-OVERRIDE');
        $manualRow[8] = '70';
        $manualUpload = $this->upload([$manualRow]);
        app(ImportMscShipments::class)->handle($manualUpload->getPathname());

        $manualShipment = MscShipment::where('bl_master', 'BL-UNIT-OVERRIDE')->firstOrFail();
        $this->assertSame(70, $manualShipment->totalUnits());

        $automaticRow = $this->row('BL-UNIT-CALCULATED');
        $automaticUpload = $this->upload([$automaticRow]);
        app(ImportMscShipments::class)->handle($automaticUpload->getPathname());
        $automaticShipment = MscShipment::where('bl_master', 'BL-UNIT-CALCULATED')->firstOrFail();
        $this->assertSame(120, $automaticShipment->totalUnits());

        $response = $this->get(route('msc.export'))->assertOk();
        $rows = $this->read($response->baseResponse->getFile()->getPathname());
        $this->assertSame('Cant contenedores', $rows[0][7]);
        $this->assertSame('Cant Unidades', $rows[0][8]);
        $this->assertSame(70, $rows[1][8]);
        $this->assertSame(120, $rows[2][8]);
    }

    public function test_import_rejects_unit_totals_above_product_container_capacity(): void
    {
        Product::firstOrFail()->update(['units_per_container' => 24]);
        $row = $this->row('BL-UNIT-OVER-CAPACITY');
        $row[8] = '121';

        Livewire::test(Import::class)->set('file', $this->upload([$row]))
            ->call('import')->assertHasErrors('file')->assertSee('Cant Unidades no puede superar la capacidad');
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_import_and_export_support_shipment_status(): void
    {
        $row = $this->row('BL-STATUS-IMPORT');
        $row[12] = 'PUERTO';
        $upload = $this->upload([$row]);
        app(ImportMscShipments::class)->handle($upload->getPathname());
        $shipment = MscShipment::where('bl_master', 'BL-STATUS-IMPORT')->firstOrFail();
        $this->assertSame('PUERTO', $shipment->status?->value);

        $response = $this->get(route('msc.export'))->assertOk();
        $rows = $this->read($response->baseResponse->getFile()->getPathname());
        $this->assertSame('Status', $rows[0][12]);
        $this->assertSame('PUERTO', $rows[1][12]);
    }

    public function test_original_workbook_layout_titles_notes_totals_and_extra_columns_are_supported(): void
    {
        $headers = ['No.', 'ETD', 'ETA', 'Bill of Lading NO.', 'Destination', 'Shipping line', 'Factory', 'Model', 'Container Qty', 'Free time', 'Ship Agent', 'RELEASED'];
        $rows = [
            ['Venezuela Shipment'], $headers,
            [500, '01/10/2026', '01/11/2026', "BL-ONE\nSECOND-001", 'Puerto cabello', 'MSC', 'Factory One', "  Model\nOne  ", 2, '21 days', 'Agent', '10-20,RELEASED'],
            ['Remark 1:The shipment already finished.'],
            ['', '', '', '', '', '', '', 'Total', '=SUM(I3:I3)'],
            ['', '', '', '', '', '`'],
        ];
        Livewire::test(Import::class)->set('file', $this->spreadsheet($rows))->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('msc_shipments', 1);
        $record = MscShipment::firstOrFail();
        $this->assertSame(1, $record->id);
        $this->assertSame('BL-ONE', $record->bl_master);
        $this->assertSame('SECOND-001', $record->bl_house);
        $this->assertSame('2026-11-01', $record->eta->format('Y-m-d'));
    }

    public function test_import_skips_repeated_bills_without_overwriting_existing_records(): void
    {
        $file = $this->upload([$this->row(), $this->row(), $this->row('BL-NEW', 'Model One', 'HOUSE-001'), $this->row('BL-SECOND')]);
        $importer = app(ImportMscShipments::class);
        $first = $importer->handle($file->getPathname());
        $second = $importer->handle($file->getPathname());
        $this->assertSame(['imported' => 3, 'skipped' => 1], $first);
        $this->assertSame(['imported' => 0, 'skipped' => 4], $second);
        $this->assertSame([1, 2, 3], MscShipment::orderBy('id')->pluck('id')->all());
        $this->assertSame('HOUSE-001', MscShipment::where('bl_master', 'BL-NEW')->where('bl_house', 'HOUSE-001')->firstOrFail()->bl_house);
    }

    public function test_unknown_product_rolls_back_the_whole_import(): void
    {
        Livewire::test(Import::class)->set('file', $this->upload([$this->row(), $this->row('BL-TWO', 'Unknown Model')]))
            ->call('import')->assertHasErrors('file')->assertSee('Fila 3');
        $this->assertDatabaseCount('msc_shipments', 0);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_invalid_dates_or_quantities_cancel_the_import(): void
    {
        foreach ([['2026-02-30', '2026-11-01', '2'], ['2026-12-01', '2026-11-01', '2'], ['2026-10-01', '2026-11-01', '1.5']] as [$etd, $eta, $qty]) {
            $row = $this->row();
            $row[1] = $etd;
            $row[2] = $eta;
            $row[7] = $qty;
            Livewire::test(Import::class)->set('file', $this->upload([$row]))->call('import')->assertHasErrors('file');
        }
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_template_contains_the_requested_columns_and_no_sample_records(): void
    {
        $response = $this->get(route('msc.template'))->assertOk()->assertDownload('plantilla-msc.xlsx');
        $this->assertSame([array_values(MscFields::HEADERS)], $this->read($response->baseResponse->getFile()->getPathname()));
    }

    public function test_export_includes_all_rows_and_can_be_reimported_without_duplicates(): void
    {
        MscShipment::factory()->count(21)->create();
        $response = $this->get(route('msc.export'))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $file = UploadedFile::fake()->createWithContent('msc.xlsx', file_get_contents($path));
        $rows = $this->read($path);
        $this->assertCount(22, $rows);
        $this->assertSame(array_values(MscFields::HEADERS), $rows[0]);
        $this->assertSame(1, $rows[1][0]);
        $this->assertSame('2026-10-01', $rows[1][1]);
        Livewire::test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('msc_shipments', 21);
    }

    public function test_export_respects_search_by_product_and_factory(): void
    {
        $factory = Factory::factory()->create(['name' => 'Special Factory']);
        $matched = MscShipment::factory()->create(['factory_id' => $factory->id]);
        MscShipment::factory()->create();
        $response = $this->get(route('msc.export', ['search' => 'Special Factory']))->assertOk();
        $rows = $this->read($response->baseResponse->getFile()->getPathname());
        $this->assertCount(2, $rows);
        $this->assertSame($matched->id, $rows[1][0]);
    }

    public function test_import_file_is_required_and_bad_headers_are_rejected(): void
    {
        Livewire::test(Import::class)->call('import')->assertHasErrors('file');
        Livewire::test(Import::class)->set('file', UploadedFile::fake()->createWithContent('msc.txt', 'ETD,ETA'))->call('import')->assertHasErrors('file');
        Livewire::test(Import::class)->set('file', $this->spreadsheet([['Wrong header'], ['Data']]))->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_command_dry_run_validates_without_creating_or_consuming_numbers(): void
    {
        $file = $this->upload([$this->row()]);
        $this->artisan('msc:import', ['file' => $file->getPathname(), '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('msc_shipments', 0);
        $this->artisan('msc:import', ['file' => $file->getPathname()])->assertSuccessful();
        $this->assertSame(1, MscShipment::firstOrFail()->id);
    }

    public function test_excel_numeric_dates_and_free_time_zero_are_supported(): void
    {
        $row = $this->row();
        $row[1] = 45931;
        $row[2] = 45962;
        $row[9] = 0;
        Livewire::test(Import::class)->set('file', $this->upload([$row]))->call('import')->assertHasNoErrors();
        $this->assertSame('2025-10-01', MscShipment::firstOrFail()->etd->format('Y-m-d'));
        $this->assertSame(0, MscShipment::firstOrFail()->free_time);
    }
}
