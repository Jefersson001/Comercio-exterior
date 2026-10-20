<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Vessels\Form;
use App\Livewire\Pages\Configuration\Vessels\Import;
use App\Livewire\Pages\Configuration\Vessels\Index;
use App\Models\MscShipment;
use App\Models\User;
use App\Models\Vessel;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class VesselManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_users_can_manage_vessels(): void
    {
        $this->get(route('configuration.vessels.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        foreach (['index', 'create', 'import', 'export', 'template'] as $action) {
            $this->get(route('configuration.vessels.'.$action))->assertOk();
        }
        Livewire::test(Form::class)->assertOk();
    }

    public function test_administrator_can_create_edit_search_and_not_delete_used_buques(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(Form::class)->set('name', '  Buque Aurora  ')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('configuration.vessels.index'));
        $vessel = Vessel::firstOrFail();
        $this->assertSame('Buque Aurora', $vessel->name);
        $this->get(route('configuration.vessels.index'))->assertOk()->assertSee($vessel->name)
            ->assertSee('data-href="'.route('configuration.vessels.edit', $vessel).'"', false);

        Livewire::test(Index::class)->set('search', 'Aurora')->assertSee($vessel->name);
        Livewire::test(Form::class, ['vessel' => $vessel])->set('name', 'Buque Boreal')->call('save')->assertHasNoErrors();
        $this->assertSame('Buque Boreal', $vessel->fresh()->name);

        $shipment = MscShipment::factory()->create(['vessel_id' => $vessel->id]);
        Livewire::test(Form::class, ['vessel' => $vessel])->call('delete')->assertHasErrors('name');
        $this->assertModelExists($vessel);
        $shipment->delete();
        Livewire::test(Form::class, ['vessel' => $vessel])->call('delete')
            ->assertRedirect(route('configuration.vessels.index'));
        $this->assertModelMissing($vessel);
    }

    public function test_vessel_import_deduplicates_names_and_template_and_export_download(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $path = tempnam(sys_get_temp_dir(), 'test-vessel-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ([['Nombre'], ['Buque Uno'], [' buque   uno '], ['Buque Dos']] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $upload = UploadedFile::fake()->createWithContent('buques.xlsx', file_get_contents($path));
        unlink($path);

        Livewire::test(Import::class)->set('file', $upload)->call('import')->assertHasNoErrors();
        $this->assertDatabaseCount('vessels', 2);
        foreach (['template', 'export'] as $action) {
            $response = $this->get(route('configuration.vessels.'.$action))->assertOk()->assertDownload();
            unlink($response->baseResponse->getFile()->getPathname());
        }
    }
}
