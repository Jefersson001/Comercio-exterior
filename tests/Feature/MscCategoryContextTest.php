<?php

namespace Tests\Feature;

use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Import;
use App\Livewire\Pages\Msc\Index;
use App\Enums\MscBillOfLadingStatus;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Support\MscFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MscCategoryContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_category_is_visible_only_in_foreign_trade_tree_and_form(): void
    {
        $category = Category::factory()->create(['name' => 'Categoría exclusiva']);
        $shipment = MscShipment::factory()->create(['category_id' => $category->id]);
        $this->get(route('msc.index'))->assertOk()->assertDontSee('Categoría exclusiva');
        $this->get(route('msc.edit', $shipment))->assertOk()->assertDontSee('id="category_id"', false);
        $this->get(route('foreign-trade.msc.index'))->assertOk()->assertSee('Categoría exclusiva')
            ->assertSee('data-href="'.route('foreign-trade.msc.edit', $shipment).'"', false)
            ->assertSee('href="'.route('foreign-trade.msc.create').'"', false);
        $this->get(route('foreign-trade.msc.edit', $shipment))->assertOk()->assertSee('id="category_id"', false)
            ->assertSee('data-foreign-trade-notebook', false)
            ->assertSee('role="tab"', false)
            ->assertSee('Fase 1')->assertSee('Fase 2')->assertSee('Fase 3')->assertSee('Fase 4')->assertSee('Notas')
            ->assertSee('data-foreign-trade-panel="phase-1"', false)
            ->assertSeeInOrder(['N°EXP.', 'DUA/INTR', 'SEABRIDGE', 'SENCAMER', 'COMEX', 'Fecha Reconocimiento', 'Fecha Recepción', 'Categoría', 'Contenedor', 'Año', 'ETD', 'ETA VNZL', 'Puerto', 'TRASEGADO', 'In bond', 'Días libres', 'Días demora', 'Abandono legal'])
            ->assertSee('Categoría exclusiva')->assertSee('href="'.route('foreign-trade.msc.index').'"', false);
        $this->get(route('msc.edit', $shipment))->assertDontSee('data-foreign-trade-notebook', false);
        $this->assertDatabaseCount('msc_shipments', 1);
    }

    public function test_foreign_trade_notebook_switches_pages_and_places_category_only_in_phase_one(): void
    {
        $category = Category::factory()->create();
        $shipment = MscShipment::factory()->create(['category_id' => $category->id]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('activeForeignTradeTab', 'phase-1')
            ->assertSee('data-foreign-trade-panel="phase-1"', false)
            ->assertSee('id="category_id"', false)
            ->call('selectForeignTradeTab', 'phase-2')
            ->assertSet('activeForeignTradeTab', 'phase-2')
            ->assertSee('data-foreign-trade-panel="phase-2"', false)
            ->assertDontSee('id="category_id"', false)
            ->call('selectForeignTradeTab', 'notes')
            ->assertSee('data-foreign-trade-panel="notes"', false)
            ->assertDontSee('id="category_id"', false);
    }

    public function test_foreign_trade_phase_one_saves_details_and_multiple_container_lines(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create();
        Livewire::test(Form::class, ['foreignTrade' => true])
            ->set('etd', '2026-10-01')
            ->set('eta', '2026-11-01')
            ->set('bl_master', 'PHASE-ONE-BL')
            ->set('product_id', $product->id)
            ->set('container_qty', '2')
            ->set('free_time', '21')
            ->set('exp_number', 'EXP-2026-A1')
            ->set('dua_intr', 'DUA/INT-82B')
            ->set('seabridge', 'SEA-001')
            ->set('sencamer', 'SEN-03C')
            ->set('comex', 'COMEX-9')
            ->set('recognition_date', '2026-10-18')
            ->set('receipt_date', '2026-10-19')
            ->set('category_id', $category->id)
            ->call('addContainerLine')
            ->set('containerLines.0.name', 'MSCU1234567')
            ->call('addContainerLine')
            ->set('containerLines.1.name', 'TGHU7654321')
            ->set('shipment_year', '2026')
            ->set('etd_vnzl', '2026-10-20')
            ->set('eta_vnzl', '2026-10-21')
            ->set('port', 'Puerto Cabello')
            ->set('trasegado', 'TRS-09A')
            ->set('in_bond_date', '2026-10-22')
            ->set('free_days', '15')
            ->set('delay_date', '2026-10-23')
            ->set('legal_abandonment_date', '2026-10-24')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('foreign-trade.msc.index'));

        $shipment = MscShipment::where('exp_number', 'EXP-2026-A1')->firstOrFail();
        $this->assertSame('DUA/INT-82B', $shipment->dua_intr);
        $this->assertSame('SEA-001', $shipment->seabridge);
        $this->assertSame('SEN-03C', $shipment->sencamer);
        $this->assertSame('COMEX-9', $shipment->comex);
        $this->assertSame('2026-10-18', $shipment->recognition_date?->format('Y-m-d'));
        $this->assertSame('2026-10-19', $shipment->receipt_date?->format('Y-m-d'));
        $this->assertSame('2026', $shipment->shipment_year);
        $this->assertSame('2026-10-20', $shipment->etd_vnzl?->format('Y-m-d'));
        $this->assertSame('2026-10-21', $shipment->eta_vnzl?->format('Y-m-d'));
        $this->assertSame('Puerto Cabello', $shipment->port);
        $this->assertSame('TRS-09A', $shipment->trasegado);
        $this->assertSame('2026-10-22', $shipment->in_bond_date?->format('Y-m-d'));
        $this->assertSame(15, $shipment->free_days);
        $this->assertSame('2026-10-23', $shipment->delay_date?->format('Y-m-d'));
        $this->assertSame('2026-10-24', $shipment->legal_abandonment_date?->format('Y-m-d'));
        $this->assertSame($category->id, $shipment->category_id);
        $this->assertSame(['MSCU1234567', 'TGHU7654321'], $shipment->containers->pluck('name')->all());
    }

    public function test_foreign_trade_phase_one_can_edit_and_remove_container_lines(): void
    {
        $shipment = MscShipment::factory()->create();
        $shipment->containers()->createMany([
            ['name' => 'CONT-ONE'],
            ['name' => 'CONT-TWO'],
        ]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('containerLines', [['name' => 'CONT-ONE'], ['name' => 'CONT-TWO']])
            ->call('removeContainerLine', 0)
            ->assertSet('containerLines', [['name' => 'CONT-TWO']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['CONT-TWO'], $shipment->fresh()->containers->pluck('name')->all());
    }

    public function test_international_form_does_not_expose_or_overwrite_foreign_trade_phase_one_data(): void
    {
        $shipment = MscShipment::factory()->create([
            'exp_number' => 'FOREIGN-EXP',
            'recognition_date' => '2026-10-18',
            'shipment_year' => '2026',
            'etd_vnzl' => '2026-10-20',
            'free_days' => 15,
        ]);
        $shipment->containers()->create(['name' => 'FOREIGN-CONT']);

        $this->get(route('msc.edit', $shipment))
            ->assertDontSee('N°EXP.')
            ->assertDontSee('CONTENEDOR')
            ->assertDontSee('ETA VNZL')
            ->assertDontSee('id="category_id"', false);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('released', 'International update')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('FOREIGN-EXP', $shipment->fresh()->exp_number);
        $this->assertSame('2026-10-18', $shipment->fresh()->recognition_date?->format('Y-m-d'));
        $this->assertSame('2026', $shipment->fresh()->shipment_year);
        $this->assertSame('2026-10-20', $shipment->fresh()->etd_vnzl?->format('Y-m-d'));
        $this->assertSame(15, $shipment->fresh()->free_days);
        $this->assertSame(['FOREIGN-CONT'], $shipment->fresh()->containers->pluck('name')->all());
    }

    public function test_foreign_trade_phase_one_validates_year_dates_and_free_days(): void
    {
        $shipment = MscShipment::factory()->create();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('shipment_year', '20A6')
            ->set('etd_vnzl', 'not-a-date')
            ->set('free_days', '-1')
            ->call('save')
            ->assertHasErrors(['shipment_year', 'etd_vnzl', 'free_days']);
    }

    public function test_foreign_trade_can_assign_clear_and_validate_the_relation(): void
    {
        $category = Category::factory()->create();
        $shipment = MscShipment::factory()->create();
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', $category->id)->call('save')->assertHasNoErrors()->assertRedirect(route('foreign-trade.msc.index'));
        $this->assertSame($category->id, $shipment->fresh()->category_id);
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', 999999)->call('save')->assertHasErrors('category_id');
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', null)->call('save')->assertHasNoErrors();
        $this->assertNull($shipment->fresh()->category_id);
    }

    public function test_status_bl_is_selectable_and_visible_only_in_foreign_trade(): void
    {
        $shipment = MscShipment::factory()->create();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('status_bl', '')
            ->set('status_bl', MscBillOfLadingStatus::PendingRelease->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MscBillOfLadingStatus::PendingRelease, $shipment->fresh()->status_bl);

        $this->get(route('foreign-trade.msc.edit', $shipment))
            ->assertSee('Status BL')
            ->assertSee('BL LIBERADO OK')
            ->assertSee('POR LIBERACION BL')
            ->assertSee('POR LIBERACION BLSIN DOCUMENTO');
        $this->get(route('msc.edit', $shipment))->assertDontSee('Status BL');

        $this->get(route('foreign-trade.msc.index'))
            ->assertSee('Status BL')
            ->assertSee('POR LIBERACION BL');
        $this->get(route('msc.index'))->assertDontSee('Status BL');
    }

    public function test_international_updates_preserve_status_bl_and_exterior_rejects_unknown_values(): void
    {
        $shipment = MscShipment::factory()->create(['status_bl' => MscBillOfLadingStatus::Released]);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSet('status_bl', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(MscBillOfLadingStatus::Released, $shipment->fresh()->status_bl);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('status_bl', 'INVALID')
            ->call('save')
            ->assertHasErrors('status_bl');
        $this->assertSame(MscBillOfLadingStatus::Released, $shipment->fresh()->status_bl);
    }

    public function test_international_updates_preserve_the_category_and_ignore_hidden_field_changes(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $factory = Factory::factory()->create();
        $shipment = MscShipment::factory()->create(['category_id' => $category->id, 'factory_id' => $factory->id]);
        Livewire::test(Form::class, ['shipment' => $shipment])->assertSet('category_id', null)
            ->set('category_id', $other->id)->call('save')->assertHasNoErrors();
        $this->assertSame($category->id, $shipment->fresh()->category_id);
        $this->assertSame($factory->id, $shipment->fresh()->factory_id);
    }

    public function test_context_cannot_be_changed_from_a_livewire_update(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Form::class)->set('foreignTrade', true);
    }

    public function test_context_and_links_persist_after_search_and_category_search_is_exterior_only(): void
    {
        $category = Category::factory()->create(['name' => 'Specific Category']);
        $shipment = MscShipment::factory()->create(['category_id' => $category->id]);
        Livewire::test(Index::class, ['foreignTrade' => true])->set('search', 'Specific Category')
            ->assertSet('foreignTrade', true)->assertSee($shipment->bl_master)
            ->assertSee('href="'.route('foreign-trade.msc.import').'"', false);
        Livewire::test(Index::class)->set('search', 'Specific Category')->assertDontSee($shipment->bl_master);
    }

    private function downloadRows(string $route): array
    {
        $response = $this->get(route($route))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
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

    public function test_only_exterior_templates_and_exports_contain_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Spare parts']);
        MscShipment::factory()->create(['category_id' => $category->id]);
        foreach (['template', 'export'] as $action) {
            $international = $this->downloadRows('msc.'.$action);
            $exterior = $this->downloadRows('foreign-trade.msc.'.$action);
            $this->assertCount(13, $international[0]);
            $this->assertCount(14, $exterior[0]);
            $this->assertSame('Categoría', $exterior[0][13]);
            if ($action === 'export') {
                $this->assertSame('Spare parts', $exterior[1][13]);
                $this->assertCount(13, $international[1]);
            }
        }
    }

    private function spreadsheet(string $bill, string $category): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test-msc-category-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(MscFields::headers(true))));
        $writer->addRow(Row::fromValues([99, '2026-10-01', '2026-11-01', $bill, '', 'Factory', 'Model One', 1, '', 21, 'Agent', '', '', $category]));
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('msc.xlsx', file_get_contents($path));
        unlink($path);

        return $file;
    }

    public function test_exterior_import_resolves_category_and_international_import_ignores_it(): void
    {
        Product::factory()->create(['name' => 'Model One']);
        $category = Category::factory()->create(['name' => 'Parts']);
        Livewire::test(Import::class, ['foreignTrade' => true])->set('file', $this->spreadsheet('FOREIGN-BL', 'Parts'))
            ->call('import')->assertHasNoErrors()->assertRedirect(route('foreign-trade.msc.index'));
        $this->assertSame($category->id, MscShipment::where('bl_master', 'FOREIGN-BL')->firstOrFail()->category_id);
        Livewire::test(Import::class)->set('file', $this->spreadsheet('INTERNATIONAL-BL', 'Parts'))->call('import')->assertHasNoErrors();
        $this->assertNull(MscShipment::where('bl_master', 'INTERNATIONAL-BL')->firstOrFail()->category_id);
        Livewire::test(Import::class, ['foreignTrade' => true])->set('file', $this->spreadsheet('UNKNOWN-CATEGORY', 'Missing'))
            ->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('msc_shipments', 2);
    }
}
