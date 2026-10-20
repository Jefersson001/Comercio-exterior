<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Categories\Form;
use App\Livewire\Pages\Configuration\Categories\Import;
use App\Livewire\Pages\Configuration\Categories\Index;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_products_flag_is_saved_and_visible_in_form_and_tree(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(Form::class)->assertSet('allows_multiple_products', false)
            ->set('name', 'Repuestos')->set('allows_multiple_products', true)
            ->call('save')->assertHasNoErrors();
        $category = Category::firstOrFail();
        $this->assertTrue($category->allows_multiple_products);
        $this->get(route('configuration.categories.index'))->assertOk()
            ->assertSee('Es para múltiples productos')->assertSee('Sí');
        Livewire::test(Form::class, ['category' => $category])
            ->assertSet('allows_multiple_products', true)->assertSee('Es para múltiples productos')
            ->set('allows_multiple_products', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($category->fresh()->allows_multiple_products);
    }

    public function test_categories_are_managed_only_by_administrators(): void
    {
        $this->get(route('configuration.categories.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        foreach (['index', 'create', 'import', 'export', 'template'] as $action) {
            $this->get(route('configuration.categories.'.$action))->assertForbidden();
        }
        Livewire::test(Form::class)->assertForbidden();
    }

    public function test_categories_have_tree_form_and_validated_unique_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(Form::class)->set('name', '  Repuestos  ')->call('save')->assertHasNoErrors()->assertRedirect(route('configuration.categories.index'));
        $category = Category::firstOrFail();
        $this->assertSame('Repuestos', $category->name);
        $this->get(route('configuration.categories.index'))->assertOk()->assertSee('Repuestos')
            ->assertSee('data-href="'.route('configuration.categories.edit', $category).'"', false);
        $this->get(route('configuration.categories.create'))->assertOk();
        $this->get(route('configuration.categories.edit', $category))->assertOk();
        Livewire::test(Form::class)->call('save')->assertHasErrors('name');
        Livewire::test(Form::class)->set('name', 'Repuestos')->call('save')->assertHasErrors('name');
        Livewire::test(Form::class)->set('name', str_repeat('x', 256))->call('save')->assertHasErrors('name');
        Livewire::test(Form::class, ['category' => $category])->set('name', 'Vehículos')->call('save')->assertHasNoErrors();
        $this->assertSame('Vehículos', $category->fresh()->name);
        Livewire::test(Index::class)->set('search', 'Vehículos')->assertSee('Vehículos');
    }

    public function test_categories_referenced_by_msc_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $category = Category::factory()->create();
        $shipment = MscShipment::factory()->create(['category_id' => $category->id]);
        Livewire::test(Form::class, ['category' => $category])->call('delete')->assertHasErrors('name');
        $this->assertModelExists($category);
        $shipment->delete();
        Livewire::test(Form::class, ['category' => $category])->call('delete')->assertRedirect(route('configuration.categories.index'));
        $this->assertModelMissing($category);
    }

    public function test_category_import_export_and_template_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $path = tempnam(sys_get_temp_dir(), 'test-category-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ([['Nombre'], ['Repuestos'], ['repuestos'], ['Vehículos']] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $upload = UploadedFile::fake()->createWithContent('categorias.xlsx', file_get_contents($path));
        unlink($path);
        Livewire::test(Import::class)->set('file', $upload)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('categories', 2);
        foreach (['template', 'export'] as $action) {
            $response = $this->get(route('configuration.categories.'.$action))->assertOk()->assertDownload();
            unlink($response->baseResponse->getFile()->getPathname());
        }
    }
}
