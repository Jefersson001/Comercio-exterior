<?php

namespace Tests\Feature;

use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Import;
use App\Livewire\Pages\Msc\Index;
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
            ->assertSee('Categoría exclusiva')->assertSee('href="'.route('foreign-trade.msc.index').'"', false);
        $this->assertDatabaseCount('msc_shipments', 1);
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
            $this->assertCount(12, $international[0]);
            $this->assertCount(13, $exterior[0]);
            $this->assertSame('Categoría', $exterior[0][12]);
            if ($action === 'export') {
                $this->assertSame('Spare parts', $exterior[1][12]);
                $this->assertCount(12, $international[1]);
            }
        }
    }

    private function spreadsheet(string $bill, string $category): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test-msc-category-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(MscFields::headers(true))));
        $writer->addRow(Row::fromValues([99, '2026-10-01', '2026-11-01', $bill, '', 'Factory', 'Model One', 1, '', 21, 'Agent', '', $category]));
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
