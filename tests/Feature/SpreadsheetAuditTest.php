<?php

namespace Tests\Feature;

use App\Actions\Categories\ImportCategories;
use App\Actions\Products\ImportProducts;
use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\UserRole;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
    }

    private function download(string $route, array $parameters = []): string
    {
        return $this->get(route($route, $parameters))->assertOk()->assertDownload()->baseResponse->getFile()->getPathname();
    }

    private function workbook(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                $xml = $zip->getFromIndex($index);
                $this->assertTrue((new DOMDocument)->loadXML($xml, LIBXML_NONET), $name);
            }
        }
        $zip->close();
        $reader = new Reader;
        $sheets = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sheets[$sheet->getName()][] = array_values($row->toArray());
                }
            }
        } finally {
            $reader->close();
            unset($reader, $sheet, $row);
        }

        return $sheets;
    }

    private function upload(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spreadsheet-audit-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    public function test_every_template_and_empty_report_is_a_valid_workbook(): void
    {
        foreach (['configuration.products', 'configuration.categories', 'configuration.factories', 'configuration.vessels', 'msc', 'foreign-trade.msc'] as $module) {
            foreach (['template', 'export'] as $action) {
                $path = $this->download($module.'.'.$action);
                try {
                    $sheets = $this->workbook($path);
                    $this->assertNotEmpty($sheets);
                    $this->assertCount(1, reset($sheets));
                } finally {
                    unlink($path);
                }
            }
        }
        foreach (['msc.export-direct', 'foreign-trade.msc.export-direct'] as $route) {
            $path = $this->download($route);
            $sheets = $this->workbook($path);
            $this->assertCount(1, reset($sheets));
            unlink($path);
        }
    }

    public function test_unknown_units_stay_blank_and_legacy_null_bl_does_not_break_any_report(): void
    {
        $product = Product::factory()->create(['units_per_container' => null]);
        MscShipment::factory()->create(['product_id' => $product->id, 'units_qty' => null, 'bl_master' => null]);
        foreach (['msc.export' => 8, 'foreign-trade.msc.export' => 14, 'msc.export-direct' => 6] as $route => $unitColumn) {
            $path = $this->download($route);
            $sheets = $this->workbook($path);
            $rows = reset($sheets);
            $this->assertSame('', $rows[1][$unitColumn]);
            $this->assertCount(2, $rows);
            unlink($path);
        }
    }

    public function test_multiple_product_summary_and_detail_match_filters_and_do_not_export_hidden_variants(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $first = Product::factory()->create(['name' => '=2+2', 'category_id' => $category->id, 'units_per_container' => 10]);
        $second = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => null]);
        $shipment = MscShipment::factory()->create([
            'category_id' => $category->id, 'product_id' => $first->id, 'container_qty' => 999,
            'status' => MscShipmentStatus::Registro, 'status_bl' => MscBillOfLadingStatus::Released,
        ]);
        $shipment->productLines()->createMany([
            ['product_id' => $first->id, 'container_qty' => 2, 'units_qty' => null],
            ['product_id' => $second->id, 'container_qty' => 3, 'units_qty' => null],
        ]);
        $variant = ProductVariant::create(['product_id' => $first->id, 'color' => 'HISTORICO OCULTO']);
        $shipment->productVariantLines()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);
        MscShipment::factory()->create(['product_id' => $second->id, 'category_id' => $category->id, 'status' => MscShipmentStatus::Puerto]);
        $parameters = ['search' => $second->name, 'status' => 'REGISTRO', 'status_bl' => MscBillOfLadingStatus::Released->value, 'category_id' => $category->id, 'foreign_trade' => true];
        foreach (['foreign-trade.msc.export' => [10, 12, 14], 'msc.export-direct' => [3, 4, 6], 'msc.export' => [7, 6, 8]] as $route => [$containerColumn, $nameColumn, $unitColumn]) {
            $path = $this->download($route, $parameters);
            $sheets = $this->workbook($path);
            $summary = reset($sheets);
            $this->assertCount(2, $summary);
            $this->assertSame(5, $summary[1][$containerColumn]);
            $this->assertSame("{$first->name}\n{$second->name}", $summary[1][$nameColumn]);
            $this->assertSame('', $summary[1][$unitColumn]);
            $this->assertCount(3, $sheets['Detalle productos']);
            $this->assertSame([2, 20], array_slice($sheets['Detalle productos'][1], 4));
            $this->assertSame([3, ''], array_slice($sheets['Detalle productos'][2], 4));
            if ($route === 'foreign-trade.msc.export') {
                $this->assertSame('', $summary[1][18]);
            }
            $zip = new ZipArchive;
            $zip->open($path);
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $part) {
                $xml = $zip->getFromName($part);
                $this->assertStringContainsString('state="frozen"', $xml);
                $this->assertStringNotContainsString('<f>', $xml);
                $this->assertStringNotContainsString('HISTORICO OCULTO', $xml);
            }
            $this->assertStringContainsString('wrapText="1"', $zip->getFromName('xl/styles.xml'));
            $zip->close();
            unlink($path);
        }
    }

    public function test_report_crosses_the_batch_boundary_without_losing_summary_or_detail_rows(): void
    {
        $first = Product::factory()->create(['units_per_container' => 10]);
        $second = Product::factory()->create(['units_per_container' => 20]);
        $shipments = MscShipment::factory()->count(501)->create(['product_id' => $first->id, 'factory_id' => null, 'vessel_id' => null]);
        foreach ([$shipments->first(), $shipments->last()] as $shipment) {
            $shipment->productLines()->createMany([
                ['product_id' => $first->id, 'container_qty' => 1, 'units_qty' => null],
                ['product_id' => $second->id, 'container_qty' => 1, 'units_qty' => 15],
            ]);
        }
        $path = $this->download('foreign-trade.msc.export');
        $sheets = $this->workbook($path);
        $this->assertCount(502, $sheets['Control carga interno']);
        $this->assertCount(5, $sheets['Detalle productos']);
        $this->assertSame($shipments->last()->bl_master, $sheets['Control carga interno'][501][7]);
        $this->assertSame($shipments->last()->bl_master, $sheets['Detalle productos'][4][1]);
        $this->assertSame(25, $sheets['Control carga interno'][501][14]);
        unlink($path);
    }

    public function test_product_export_and_import_preserve_category_and_legacy_imports_remain_supported(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'units_per_container' => 24]);
        $path = $this->download('configuration.products.export');
        $sheets = $this->workbook($path);
        $this->assertSame([$product->name, 24, $category->name, ''], $sheets['Productos'][1]);
        $product->update(['category_id' => null, 'units_per_container' => null]);
        app(ImportProducts::class)->handle($path);
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame(24, $product->fresh()->units_per_container);
        unlink($path);
        $legacy = $this->upload([['Nombre'], [$product->name]]);
        app(ImportProducts::class)->handle($legacy);
        $this->assertSame($category->id, $product->fresh()->category_id);
        unlink($legacy);
    }

    public function test_category_export_and_import_preserve_the_boolean_and_accept_legacy_files(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $path = $this->download('configuration.categories.export');
        $sheets = $this->workbook($path);
        $this->assertSame([$category->name, 'Sí'], $sheets['Categorías'][1]);
        $category->update(['allows_multiple_products' => false]);
        $result = app(ImportCategories::class)->handle($path);
        $this->assertSame(1, $result['updated']);
        $this->assertTrue($category->fresh()->allows_multiple_products);
        unlink($path);
        $legacy = $this->upload([['Nombre'], [$category->name]]);
        app(ImportCategories::class)->handle($legacy);
        $this->assertTrue($category->fresh()->allows_multiple_products);
        unlink($legacy);
        $path = $this->upload([['Nombre', 'Es para múltiples productos'], ['Individual', false], ['Múltiple', 1]]);
        app(ImportCategories::class)->handle($path);
        $this->assertDatabaseHas('categories', ['name' => 'Individual', 'allows_multiple_products' => false]);
        $this->assertDatabaseHas('categories', ['name' => 'Múltiple', 'allows_multiple_products' => true]);
        unlink($path);
    }

    public function test_invalid_new_catalog_fields_roll_back_all_changes(): void
    {
        $category = Category::factory()->create(['allows_multiple_products' => true]);
        $product = Product::factory()->create(['units_per_container' => 10]);
        $path = $this->upload([['Nombre', 'Unidades por contenedor', 'Categoría'], [$product->name, 20, $category->name], ['Inválido', 30, 'Inexistente']]);
        try {
            app(ImportProducts::class)->handle($path);
            $this->fail('Debe rechazar una categoría inexistente.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Fila 3', $exception->getMessage());
        }
        unset($exception);
        gc_collect_cycles();
        unlink($path);
        $this->assertSame(10, $product->fresh()->units_per_container);
        $this->assertNull($product->fresh()->category_id);
        $path = $this->upload([['Nombre', 'Es para múltiples productos'], [$category->name, 'No'], ['Inválida', 'tal vez']]);
        try {
            app(ImportCategories::class)->handle($path);
            $this->fail('Debe rechazar un booleano inválido.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Fila 3', $exception->getMessage());
        }
        unset($exception);
        gc_collect_cycles();
        unlink($path);
        $this->assertTrue($category->fresh()->allows_multiple_products);
    }
}
