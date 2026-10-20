<?php

namespace Tests\Feature;

use App\Actions\Msc\ImportMscShipments;
use App\Livewire\Pages\Msc\Form;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class MscProductLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
    }

    private function form(Category $category): Testable
    {
        return Livewire::test(Form::class, ['foreignTrade' => true])
            ->set('etd', '2026-10-01')->set('eta', '2026-11-01')
            ->set('bl_master', 'MULTI-PRODUCT')->set('free_time', '21')
            ->set('pendingCategoryId', $category->id);
    }

    public function test_multiple_product_lines_save_totals_without_variants_and_reopen(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $first = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 10]);
        $second = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 20]);
        $variant = ProductVariant::create(['product_id' => $second->id, 'color' => 'Rojo']);
        $lines = [
            ['product_id' => $first->id, 'container_qty' => '2', 'units_qty' => ''],
            ['product_id' => $second->id, 'container_qty' => '3', 'units_qty' => '45'],
        ];
        $this->form($category)->set('productLines', $lines)
            ->set('productVariantLines', [['product_variant_id' => $variant->id, 'quantity' => '5']])
            ->call('save')->assertHasNoErrors();
        $shipment = MscShipment::firstOrFail();
        $this->assertCount(2, $shipment->productLines);
        $this->assertSame(5, $shipment->container_qty);
        $this->assertSame(65, $shipment->totalUnits());
        $this->assertSame($first->id, $shipment->product_id);
        $this->assertCount(0, $shipment->productVariantLines);
        $displayLines = $lines;
        $displayLines[0]['units_qty'] = '20';
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('productLines', $displayLines)->assertSee($first->name)->assertSee($second->name)
            ->assertDontSee('id="product_id"', false)->assertDontSee('id="container_qty"', false)
            ->assertDontSee('Variante del producto')
            ->call('addProductVariantLine')->assertSet('productVariantLines', [])
            ->assertSee('Agregar línea de producto');

        foreach (['msc.index', 'foreign-trade.msc.index'] as $route) {
            $this->get(route($route, ['search' => $second->name]))->assertOk()->assertSee($shipment->bl_master)->assertSee($second->name);
        }
        Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSee('Administra sus líneas desde Comercio Exterior')
            ->set('product_id', $second->id)->set('container_qty', '999')->set('units_qty', '999')
            ->set('released', 'Actualizado')->call('save')->assertHasNoErrors();
        $this->assertSame(5, $shipment->fresh()->container_qty);
        $this->assertCount(2, $shipment->fresh()->productLines);
    }

    public function test_individual_category_limits_lines_and_rejects_products_from_other_categories(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $other = Product::factory()->create();
        $form = $this->form($category)->call('addProductLine')
            ->assertSee($product->name)->assertDontSee($other->name)->assertDontSee('Agregar línea de producto')
            ->call('addProductLine')->assertCount('productLines', 1);
        $form->set('productLines', [
            ['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => ''],
            ['product_id' => $other->id, 'container_qty' => '1', 'units_qty' => ''],
        ])->call('save')->assertHasErrors(['productLines', 'productLines.1.product_id']);
        $this->assertDatabaseCount('msc_shipments', 0);
        $form->set('productLines', [['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '']])
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('msc_shipment_products', 1);
    }

    public function test_multiple_category_hides_and_preserves_historical_variants_without_allowing_edits(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Histórico']);
        $shipment = MscShipment::factory()->create(['category_id' => $category->id, 'product_id' => $product->id]);
        $shipment->productVariantLines()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);
        $category->update(['allows_multiple_products' => true]);
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertDontSee('Variante del producto')
            ->call('addProductVariantLine')->assertCount('productVariantLines', 1)
            ->call('removeProductVariantLine', 0)->assertCount('productVariantLines', 1)
            ->set('productVariantLines', [['product_variant_id' => $variant->id, 'quantity' => '999']])
            ->call('save')->assertHasNoErrors();
        $this->assertSame(5, $shipment->fresh()->productVariantLines->sole()->quantity);
        $category->update(['allows_multiple_products' => false]);
        Livewire::test(Form::class, ['shipment' => $shipment->fresh(), 'foreignTrade' => true])
            ->assertSee('Variante del producto')->assertSet('productVariantLines.0.quantity', '5');
    }

    public function test_category_changes_require_confirmation_and_preserve_saved_lines_until_save(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $next = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $shipment = MscShipment::factory()->create(['category_id' => $category->id, 'product_id' => $product->id]);
        $shipment->syncSingleProductLine();
        $form = Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('pendingCategoryId', $next->id)->assertSet('confirmingCategoryChange', true)
            ->assertSet('category_id', $category->id)->assertCount('productLines', 1)
            ->call('save')->assertHasErrors('category_id')
            ->call('cancelCategoryChange')->assertSet('pendingCategoryId', $category->id)->assertCount('productLines', 1)
            ->set('pendingCategoryId', $next->id)->call('applyCategoryChange')
            ->assertSet('category_id', $next->id)->assertCount('productLines', 0);
        $this->assertSame($category->id, $shipment->fresh()->category_id);
        $this->assertCount(1, $shipment->fresh()->productLines);
        $form->call('save')->assertHasErrors('productLines');
    }

    public function test_switching_multiple_category_to_individual_never_discards_extra_lines(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id]);
        $form = $this->form($category)->set('productLines', $products->map(fn ($product): array => [
            'product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '',
        ])->all());
        $category->update(['allows_multiple_products' => false]);
        $form->call('save')->assertHasErrors('productLines')->assertCount('productLines', 2);
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_reports_include_product_details_and_totals_without_losing_lines(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'units_per_container' => 10]);
        $this->form($category)->set('productLines', $products->map(fn ($product): array => [
            'product_id' => $product->id, 'container_qty' => '2', 'units_qty' => '15',
        ])->all())->call('save')->assertHasNoErrors();
        foreach (['foreign-trade.msc.export', 'msc.export-direct', 'msc.export'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $reader = new Reader;
            $sheets = [];
            try {
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $sheets[$sheet->getName()][] = $row->toArray();
                    }
                }
            } finally {
                $reader->close();
                unset($reader, $sheet, $row);
            }
            $this->assertCount(3, $sheets['Detalle productos']);
            $this->assertSame($products[0]->name, $sheets['Detalle productos'][1][3]);
            $this->assertSame(2, $sheets['Detalle productos'][2][4]);
            $this->assertSame(15, $sheets['Detalle productos'][2][5]);
            if ($route === 'msc.export') {
                try {
                    app(ImportMscShipments::class)->handle($path);
                    $this->fail('Una plantilla de un solo producto no debe sobrescribir un embarque múltiple.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString('contiene varios productos', $exception->getMessage());
                }
                unset($exception);
                gc_collect_cycles();
                $this->assertDatabaseCount('msc_shipment_products', 2);
            }
            unlink($path);
        }
    }

    public function test_line_validation_protects_capacity_duplicates_and_totals(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 10]);
        $form = $this->form($category)->set('productLines', [['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '11']])
            ->call('save')->assertHasErrors('productLines.0.units_qty');
        $form->set('productLines', [
            ['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '5'],
            ['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '5'],
        ])->call('save')->assertHasErrors('productLines.0.product_id');
        $form->set('productLines', [['product_id' => $product->id, 'container_qty' => '2147483647', 'units_qty' => '']])
            ->call('save')->assertHasErrors('productLines.0.units_qty');
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_line_quantities_recalculate_in_both_directions_and_preserve_manual_overrides(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $first = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 24]);
        $second = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 10]);
        $third = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 5]);
        $form = $this->form($category)->set('productLines', [
            ['product_id' => $first->id, 'container_qty' => '1', 'units_qty' => ''],
            ['product_id' => $second->id, 'container_qty' => '2', 'units_qty' => '15'],
        ])->assertSet('productLines.0.units_qty', '24')
            ->set('productLines.0.container_qty', '3')->assertSet('productLines.0.units_qty', '72')
            ->set('productLines.0.units_qty', '70')->assertSet('productLines.0.container_qty', '3')
            ->set('productLines.0.units_qty', '73')->assertSet('productLines.0.container_qty', '4')
            ->assertSet('productLines.0.units_qty', '73')
            ->set('productLines.0.container_qty', '5')->assertSet('productLines.0.units_qty', '120')
            ->set('productLines.0.units_qty', '')->assertSet('productLines.0.units_qty', '120')
            ->assertSet('lineUnitsOverridden.0', false)
            ->set('productLines.0.product_id', $third->id)->assertSet('productLines.0.units_qty', '25')
            ->set('productLines.0.units_qty', '21')->assertSet('productLines.0.container_qty', '5')
            ->assertSet('productLines.1.container_qty', '2')->assertSet('productLines.1.units_qty', '15')
            ->call('save')->assertHasNoErrors();
        $shipment = MscShipment::firstOrFail();
        $this->assertSame(7, $shipment->container_qty);
        $this->assertSame(36, $shipment->totalUnits());
        $this->assertSame(21, $shipment->productLines[0]->units_qty);
        $this->assertSame(15, $shipment->productLines[1]->units_qty);
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('productLines.0.units_qty', '21')
            ->set('productLines.1.container_qty', '3')->assertSet('productLines.1.units_qty', '30')
            ->call('removeProductLine', 0)->assertSet('lineUnitsOverridden.0', false)
            ->call('save')->assertHasNoErrors();
        $this->assertNull($shipment->fresh()->productLines->sole()->units_qty);
        $this->assertSame(30, $shipment->fresh()->totalUnits());
    }

    public function test_single_product_line_recalculates_and_handles_products_without_capacity(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 24]);
        $unknown = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => null]);
        $this->form($category)->call('addProductLine')
            ->assertSee('Selecciona un producto para calcular las unidades.')
            ->set('productLines.0.product_id', $product->id)->assertSet('productLines.0.units_qty', '24')
            ->assertSee('24 unidades por contenedor.')
            ->set('productLines.0.units_qty', '49')->assertSet('productLines.0.container_qty', '3')
            ->set('productLines.0.product_id', $unknown->id)->assertSet('productLines.0.units_qty', '')
            ->assertSee('Este producto no tiene unidades por contenedor configuradas.')
            ->set('productLines.0.units_qty', '50')->assertSet('productLines.0.container_qty', '3')
            ->set('productLines.0.units_qty', '')->assertSet('productLines.0.units_qty', '')
            ->call('save')->assertHasNoErrors();
        $this->assertNull(MscShipment::firstOrFail()->totalUnits());
    }

    public function test_migration_preserves_existing_products_quantities_and_automatic_units(): void
    {
        $migration = require database_path('migrations/2026_10_20_190000_create_msc_shipment_products_table.php');
        $migration->down();
        $product = Product::factory()->create(['units_per_container' => 20]);
        $manual = MscShipment::factory()->create(['product_id' => $product->id, 'container_qty' => 3, 'units_qty' => 45]);
        $automatic = MscShipment::factory()->create(['product_id' => $product->id, 'container_qty' => 2, 'units_qty' => null]);
        $migration->up();
        $this->assertSame($product->id, $manual->productLines->sole()->product_id);
        $this->assertSame(45, $manual->totalUnits());
        $this->assertNull($automatic->productLines->sole()->units_qty);
        $this->assertSame(40, $automatic->totalUnits());
        Livewire::test(Form::class, ['shipment' => $automatic])
            ->assertSet('unitsQtyOverridden', false)->call('save')->assertHasNoErrors();
        $this->assertNull($automatic->fresh()->productLines->sole()->units_qty);
    }

    public function test_secondary_products_cannot_be_deleted_while_assigned_to_a_shipment(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id]);
        $this->form($category)->set('productLines', $products->map(fn ($product): array => [
            'product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '1',
        ])->all())->call('save')->assertHasNoErrors();
        Livewire::test(\App\Livewire\Pages\Configuration\Products\Form::class, ['product' => $products[1]])
            ->call('delete')->assertHasErrors('name');
        $this->assertModelExists($products[1]);
    }
}
