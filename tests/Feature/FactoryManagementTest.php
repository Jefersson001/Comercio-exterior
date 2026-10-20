<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Factories\Form;
use App\Livewire\Pages\Configuration\Factories\Import;
use App\Livewire\Pages\Configuration\Factories\Index;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class FactoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_users_can_manage_factories(): void
    {
        $this->get(route('configuration.factories.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        foreach (['index', 'create', 'import', 'export', 'template'] as $action) {
            $this->get(route('configuration.factories.'.$action))->assertOk();
        }
        Livewire::test(Form::class)->assertOk();
    }

    public function test_administrator_can_create_edit_search_and_not_delete_used_factories(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(Form::class)->set('name', '  Fábrica Central  ')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('configuration.factories.index'));
        $factory = Factory::firstOrFail();
        $this->assertSame('Fábrica Central', $factory->name);
        $this->get(route('configuration.factories.index'))->assertOk()->assertSee($factory->name)
            ->assertSee('data-href="'.route('configuration.factories.edit', $factory).'"', false);

        Livewire::test(Index::class)->set('search', 'Central')->assertSee($factory->name);
        Livewire::test(Form::class, ['factory' => $factory])->set('name', 'Fábrica Norte')->call('save')->assertHasNoErrors();
        $this->assertSame('Fábrica Norte', $factory->fresh()->name);

        $shipment = MscShipment::factory()->create(['factory_id' => $factory->id]);
        Livewire::test(Form::class, ['factory' => $factory])->call('delete')->assertHasErrors('name');
        $this->assertModelExists($factory);
        $shipment->delete();
        Livewire::test(Form::class, ['factory' => $factory])->call('delete')
            ->assertRedirect(route('configuration.factories.index'));
        $this->assertModelMissing($factory);
    }

    public function test_factory_import_deduplicates_names_and_template_and_export_download(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $path = tempnam(sys_get_temp_dir(), 'test-factory-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ([['Nombre'], ['Planta Uno'], [' planta   uno '], ['Planta Dos']] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $upload = UploadedFile::fake()->createWithContent('fabricas.xlsx', file_get_contents($path));
        unlink($path);

        Livewire::test(Import::class)->set('file', $upload)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('factories', 2);
        foreach (['template', 'export'] as $action) {
            $response = $this->get(route('configuration.factories.'.$action))->assertOk()->assertDownload();
            unlink($response->baseResponse->getFile()->getPathname());
        }
    }
}
