<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Products\Import;
use App\Models\Product;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class ProductSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private function signInAdministrator(): User
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin);

        return $admin;
    }

    /** @param list<list<string>> $rows */
    private function spreadsheet(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test-products-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value), $row)));
        }
        $writer->close();
        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('productos.xlsx', $content);
    }

    /** @return list<list<mixed>> */
    private function readSpreadsheet(string $path): array
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

    public function test_spreadsheet_routes_are_restricted_to_administrators(): void
    {
        $urls = [route('configuration.products.import'), route('configuration.products.export'), route('configuration.products.template')];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        Livewire::test(Import::class)->assertForbidden();
    }

    public function test_import_page_and_list_show_the_spreadsheet_actions(): void
    {
        $this->signInAdministrator();
        $this->get(route('configuration.products.index'))->assertOk()
            ->assertSee('Importar productos')->assertSee('Exportar productos')->assertSee('Descargar plantilla');
        $this->get(route('configuration.products.import'))->assertOk()->assertSee('Archivo de productos');
    }

    public function test_template_is_a_real_excel_file_with_name_and_units_headers(): void
    {
        $this->signInAdministrator();
        $response = $this->get(route('configuration.products.template'))->assertOk()->assertDownload('plantilla-productos.xlsx');
        $rows = $this->readSpreadsheet($response->baseResponse->getFile()->getPathname());
        $this->assertSame([['Nombre', 'Unidades por contenedor']], $rows);
    }

    public function test_export_includes_all_products_across_pages_with_units_per_container(): void
    {
        $this->signInAdministrator();
        Product::factory()->count(20)->create();
        Product::query()->orderBy('name')->orderBy('id')->update(['units_per_container' => 24]);
        $expected = Product::orderBy('name')->orderBy('id')->get()->map(fn (Product $product): array => [$product->name, 24])->all();
        $response = $this->get(route('configuration.products.export'))->assertOk()->assertDownload();
        $rows = $this->readSpreadsheet($response->baseResponse->getFile()->getPathname());
        $this->assertSame([['Nombre', 'Unidades por contenedor'], ...$expected], $rows);
    }

    public function test_export_respects_search_and_accepts_an_empty_catalog(): void
    {
        $this->signInAdministrator();
        $response = $this->get(route('configuration.products.export'))->assertOk();
        $this->assertSame([['Nombre', 'Unidades por contenedor']], $this->readSpreadsheet($response->baseResponse->getFile()->getPathname()));
        Product::factory()->create(['name' => 'Producto filtrado']);
        Product::factory()->create(['name' => 'Otro']);
        $response = $this->get(route('configuration.products.export', ['search' => 'filtrado']))->assertOk();
        $this->assertSame([['Nombre', 'Unidades por contenedor'], ['Producto filtrado', '']], $this->readSpreadsheet($response->baseResponse->getFile()->getPathname()));
        $this->getJson(route('configuration.products.export', ['search' => ['invalid']]))->assertUnprocessable();
    }

    public function test_export_writes_names_as_text_even_when_they_start_with_equals(): void
    {
        $this->signInAdministrator();
        Product::factory()->create(['name' => '=1+1']);
        $response = $this->get(route('configuration.products.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        $xml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        unlink($path);

        $this->assertIsString($xml);
        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringContainsString('=1+1', $xml);
        $this->assertStringNotContainsString('<f', $xml);
    }

    public function test_import_creates_products_and_skips_case_insensitive_duplicates_and_empty_rows(): void
    {
        $this->signInAdministrator();
        Product::factory()->create(['name' => 'Existente']);
        $file = $this->spreadsheet([
            ['Nombre', 'Unidades por contenedor'], ['Nuevo', '24'], [' NUEVO ', '25'],
            ['existente', '48'], [' ', ''], ['Café', '12'],
        ]);
        Livewire::test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors()
            ->assertRedirect(route('configuration.products.index'));
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseHas('products', ['name' => 'Nuevo']);
        $this->assertDatabaseHas('products', ['name' => 'Nuevo', 'units_per_container' => 24]);
        $this->assertDatabaseHas('products', ['name' => 'Existente', 'units_per_container' => 48]);
        $this->assertDatabaseHas('products', ['name' => 'Café']);
        $this->assertStringContainsString('2 productos agregados, 1 actualizados y 2', session('status'));
    }

    public function test_export_can_be_imported_without_creating_duplicates(): void
    {
        $this->signInAdministrator();
        Product::factory()->create(['name' => 'Producto original', 'units_per_container' => 12]);
        Product::factory()->create(['name' => '=1+1', 'units_per_container' => 6]);
        $response = $this->get(route('configuration.products.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $file = UploadedFile::fake()->createWithContent('productos.xlsx', file_get_contents($path));
        unlink($path);
        Livewire::test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', ['name' => 'Producto original', 'units_per_container' => 12]);
    }

    public function test_import_validates_required_file_and_format(): void
    {
        $this->signInAdministrator();
        Livewire::test(Import::class)->call('import')->assertHasErrors(['file' => 'required']);
        Livewire::test(Import::class)->set('file', UploadedFile::fake()->createWithContent('productos.txt', 'Nombre'))
            ->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_incorrect_header_extra_columns_and_header_only_are_rejected(): void
    {
        $this->signInAdministrator();
        foreach ([[['Incorrecto'], ['Producto']], [['Nombre', 'Precio'], ['Producto', '20']], [['Nombre', 'Unidades por contenedor']]] as $rows) {
            Livewire::test(Import::class)->set('file', $this->spreadsheet($rows))->call('import')->assertHasErrors('file');
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_legacy_name_only_spreadsheets_still_import_products(): void
    {
        $this->signInAdministrator();
        Livewire::test(Import::class)->set('file', $this->spreadsheet([['Nombre'], ['Producto antiguo']]))
            ->call('import')->assertHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Producto antiguo', 'units_per_container' => null]);
    }

    public function test_invalid_units_cancel_the_entire_import(): void
    {
        $this->signInAdministrator();
        $file = $this->spreadsheet([['Nombre', 'Unidades por contenedor'], ['Correcto', '12'], ['Incorrecto', '1.5']]);
        Livewire::test(Import::class)->set('file', $file)->call('import')->assertHasErrors('file')->assertSee('Fila 3');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_row_rolls_back_even_previously_inserted_batches(): void
    {
        $this->signInAdministrator();
        $rows = [['name']];
        for ($index = 1; $index <= 501; $index++) {
            $rows[] = ['Producto '.$index];
        }
        $rows[] = [str_repeat('a', 256)];
        Livewire::test(Import::class)->set('file', $this->spreadsheet($rows))->call('import')->assertHasErrors('file')->assertSee('Fila 503');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_permissions_are_checked_again_before_importing(): void
    {
        $admin = $this->signInAdministrator();
        $component = Livewire::test(Import::class)->set('file', $this->spreadsheet([['Nombre'], ['Producto']]));
        $admin->role = UserRole::User;
        $admin->save();
        $component->call('import')->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }
}
