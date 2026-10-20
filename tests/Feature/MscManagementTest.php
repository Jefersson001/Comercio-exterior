<?php

namespace Tests\Feature;

use App\Enums\MscShipmentStatus;
use App\Livewire\Pages\Configuration\Products\Form as ProductForm;
use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Index;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vessel;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MscManagementTest extends TestCase
{
    use RefreshDatabase;

    private function data(Product $product, array $overrides = []): array
    {
        return array_replace([
            'etd' => '2026-10-01', 'eta' => '2026-11-01', 'bl_master' => 'BL-001', 'bl_house' => '',
            'factory_id' => Factory::factory()->create()->id, 'product_id' => $product->id, 'container_qty' => '5',
            'units_qty' => '', 'free_time' => '21', 'vessel_id' => Vessel::factory()->create()->id, 'released' => '',
        ], $overrides);
    }

    public function test_guests_cannot_access_msc_pages(): void
    {
        $shipment = MscShipment::factory()->create();
        foreach ([route('msc.index'), route('msc.create'), route('msc.edit', $shipment), route('msc.import'), route('msc.export'), route('msc.export-direct'), route('msc.template')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_both_menus_link_to_the_same_list_and_form(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = MscShipment::factory()->create();
        $response = $this->get(route('msc.index'))->assertOk()->assertSee($shipment->bl_master)->assertSee('BL Master')->assertSee('BL House');
        $response->assertSee('href="'.route('msc.index').'"', false)
            ->assertSee('href="'.route('foreign-trade.msc.index').'"', false);
        $response->assertSee('Reporte Control Carga Interno')
            ->assertSee('Reporte Control Carga Direct')
            ->assertSee('href="'.route('msc.export', ['search' => '']).'"', false)
            ->assertSee('href="'.route('msc.export-direct', ['search' => '']).'"', false);
        $this->get(route('foreign-trade.msc.index'))->assertOk()
            ->assertSee('Reporte Control Carga Interno')
            ->assertSee('Reporte Control Carga Direct')
            ->assertSee('href="'.route('msc.export', ['search' => '']).'"', false)
            ->assertSee('href="'.route('msc.export-direct', ['search' => '']).'"', false);
        $response->assertSee('data-href="'.route('msc.edit', $shipment).'"', false);
        $this->get(route('msc.create'))->assertOk()->assertSee('Se asignará al guardar');
        $this->get(route('msc.edit', $shipment))->assertOk()->assertSee('MSC No. '.$shipment->id);
        Livewire::test(Form::class, ['shipment' => $shipment])->assertSet('product_id', $shipment->product_id)
            ->assertSet('etd', '2026-10-01')->assertSet('container_qty', '2');
    }

    public function test_legacy_shipment_with_null_bill_of_lading_fields_can_be_opened_for_editing(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = MscShipment::factory()->create();
        $shipment->forceFill(['bl_master' => null])->save();

        $this->get(route('msc.edit', $shipment))->assertOk();
        Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSet('bl_master', '')
            ->assertSet('bl_house', '');
    }

    public function test_creating_msc_assigns_the_next_system_number_and_product_relation(): void
    {
        $this->actingAs(User::factory()->create());
        $previous = MscShipment::factory()->create();
        $product = Product::factory()->create();
        Livewire::test(Form::class)->fill($this->data($product, ['bl_house' => 'HOUSE-001']))->call('save')->assertHasNoErrors()->assertRedirect(route('msc.index'));
        $created = MscShipment::where('bl_master', 'BL-001')->firstOrFail();
        $this->assertSame($previous->id + 1, $created->id);
        $this->assertSame($product->id, $created->product->id);
        $this->assertSame('HOUSE-001', $created->bl_house);
        $this->assertSame(21, $created->free_time);
    }

    public function test_msc_calculates_unit_count_from_product_and_container_count_and_allows_an_override(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['units_per_container' => 24]);
        $form = Livewire::test(Form::class)->fill($this->data($product, ['container_qty' => '3']));
        $form->assertSet('units_qty', '72')->call('save')->assertHasNoErrors();

        $shipment = MscShipment::where('bl_master', 'BL-001')->firstOrFail();
        $this->assertNull($shipment->units_qty);
        $this->assertSame(72, $shipment->totalUnits());

        Livewire::test(Form::class, ['shipment' => $shipment])->set('units_qty', '70')->call('save')->assertHasNoErrors();
        $this->assertSame(70, $shipment->fresh()->totalUnits());
        Livewire::test(Form::class, ['shipment' => $shipment])->set('units_qty', '73')->call('save')->assertHasNoErrors();
        $this->assertSame(4, $shipment->fresh()->container_qty);
        $this->assertSame(73, $shipment->fresh()->totalUnits());

        $product->update(['units_per_container' => 30]);
        $this->assertSame(73, $shipment->fresh()->totalUnits());
    }

    public function test_dynamic_unit_total_tracks_catalog_updates_until_an_override_is_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['units_per_container' => 10]);
        $shipment = MscShipment::factory()->create(['product_id' => $product->id, 'container_qty' => 4, 'units_qty' => null]);
        $this->assertSame(40, $shipment->totalUnits());
        $product->update(['units_per_container' => 15]);
        $this->assertSame(60, $shipment->fresh()->totalUnits());
    }

    public function test_changing_product_or_container_count_recalculates_units_and_clears_manual_override(): void
    {
        $this->actingAs(User::factory()->create());
        $firstProduct = Product::factory()->create(['units_per_container' => 20]);
        $secondProduct = Product::factory()->create(['units_per_container' => 12]);
        $form = Livewire::test(Form::class)
            ->set('product_id', $firstProduct->id)
            ->set('container_qty', '3')
            ->assertSet('units_qty', '60')
            ->set('units_qty', '55')
            ->set('product_id', $secondProduct->id)
            ->assertSet('units_qty', '36')
            ->set('units_qty', '33')
            ->set('container_qty', '4')
            ->assertSet('units_qty', '48');

        $form->assertHasNoErrors();
    }

    public function test_manual_unit_total_adjusts_container_count_for_partial_last_container(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['units_per_container' => 50]);
        $shipment = MscShipment::factory()->create([
            'product_id' => $product->id,
            'container_qty' => 2,
            'units_qty' => null,
        ]);

        $form = Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSet('units_qty', '100')
            ->set('units_qty', '75')
            ->assertSet('container_qty', '2')
            ->set('units_qty', '48')
            ->assertSet('container_qty', '1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $shipment->fresh()->container_qty);
        $this->assertSame(48, $shipment->fresh()->totalUnits());
    }

    public function test_shipment_status_can_be_set_changed_and_cleared_by_msc_users(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = MscShipment::factory()->create(['status' => null]);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSet('status', '')
            ->set('status', MscShipmentStatus::Puerto->value)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(MscShipmentStatus::Puerto, $shipment->fresh()->status);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('status', MscShipmentStatus::Reconocimiento->value)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(MscShipmentStatus::Reconocimiento, $shipment->fresh()->status);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('status', 'INVALIDO')
            ->call('save')
            ->assertHasErrors('status');
        $this->assertSame(MscShipmentStatus::Reconocimiento, $shipment->fresh()->status);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('status', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($shipment->fresh()->status);
    }

    public function test_shipment_status_bar_is_visible_in_list_and_edit_form(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = MscShipment::factory()->create(['status' => MscShipmentStatus::Navegando]);

        $this->get(route('msc.index'))->assertOk()->assertSee('NAVEGANDO')->assertSee('RECONOCIMIENTO');
        $this->get(route('msc.edit', $shipment))->assertOk()
            ->assertSeeInOrder(['Status del embarque', 'Borrador', 'IN BOND', 'NAVEGANDO', 'RECONOCIMIENTO', 'No.'])
            ->assertSee('NAVEGANDO');
    }

    public function test_form_validates_dates_product_and_integer_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();
        Livewire::test(Form::class)->fill($this->data($product, [
            'etd' => '2026-11-01', 'eta' => '2026-10-01', 'product_id' => 99999,
            'container_qty' => '1.5', 'free_time' => '-1', 'bl_master' => '', 'factory_id' => 999999,
        ]))->call('save')->assertHasErrors(['eta', 'product_id', 'container_qty', 'free_time', 'bl_master', 'factory_id']);
        $this->assertDatabaseCount('msc_shipments', 0);
    }

    public function test_editing_preserves_the_number_and_requires_unique_master_house_pair(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = MscShipment::factory()->create();
        $other = MscShipment::factory()->create();
        $factory = Factory::factory()->create();
        Livewire::test(Form::class, ['shipment' => $shipment])->set('factory_id', $factory->id)->call('save')->assertHasNoErrors();
        $this->assertSame($factory->id, $shipment->fresh()->factory_id);
        Livewire::test(Form::class, ['shipment' => $shipment])->set('bl_master', $other->bl_master)->call('save')->assertHasErrors('bl_master');
        Livewire::test(Form::class, ['shipment' => $shipment])->set('bl_master', $other->bl_master)->set('bl_house', 'HOUSE-001')->call('save')->assertHasNoErrors();
    }

    public function test_msc_can_be_deleted_but_related_products_are_protected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $shipment = MscShipment::factory()->create();
        $product = $shipment->product;
        Livewire::test(ProductForm::class, ['product' => $product])->call('delete')->assertHasErrors('name');
        $this->assertModelExists($product);
        Livewire::test(Form::class, ['shipment' => $shipment])->call('delete')->assertRedirect(route('msc.index'));
        $this->assertModelMissing($shipment);
        $this->assertModelExists($product);
    }

    public function test_search_includes_the_related_product_factory_and_number(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Unique Model']);
        $factory = Factory::factory()->create(['name' => 'Factory Match']);
        $vessel = Vessel::factory()->create(['name' => 'Vessel Match']);
        $shipment = MscShipment::factory()->create(['product_id' => $product->id, 'factory_id' => $factory->id, 'vessel_id' => $vessel->id, 'bl_house' => 'HOUSE-LOOKUP']);
        $otherFactory = Factory::factory()->create(['name' => 'Different Factory']);
        $other = MscShipment::factory()->create(['bl_master' => 'UNRELATED-BILL', 'factory_id' => $otherFactory->id]);
        foreach (['Unique Model', 'Factory Match', 'Vessel Match', 'HOUSE-LOOKUP', (string) $shipment->id] as $search) {
            Livewire::test(Index::class)->set('search', $search)->assertSee($shipment->bl_master)->assertDontSee($other->bl_master);
        }
    }

    public function test_missing_shipments_and_inactive_accounts_cannot_be_used(): void
    {
        $this->actingAs(User::factory()->create())->get('/msc/999999/editar')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_active' => false]))->get(route('msc.index'))->assertRedirect(route('login'));
    }
}
