<?php

namespace Tests\Feature;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Livewire\Pages\Home\Dashboard;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vessel;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_eta_notice_includes_boundary_and_keeps_shipment_until_arrival(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $this->travelTo(Carbon::parse('2026-10-19 12:00', 'America/Caracas'));
        $shipment = MscShipment::factory()->create(['eta' => '2026-10-30', 'status' => MscShipmentStatus::Navegando]);
        $dashboard = Livewire::test(Dashboard::class)
            ->assertViewHas('upcomingArrivals', fn ($rows) => $rows->total() === 0);

        foreach (['2026-10-20' => 'Faltan 10 días', '2026-10-25' => 'Faltan 5 días', '2026-10-30' => 'Llega hoy'] as $date => $label) {
            $this->travelTo(Carbon::parse($date.' 12:00', 'America/Caracas'));
            $dashboard->call('$refresh')->assertSee($label)
                ->assertViewHas('upcomingArrivals', fn ($rows) => $rows->total() === 1 && $rows->first()->is($shipment));
        }
        // UTC is already October 31, but Venezuela is still on October 30.
        $this->travelTo(Carbon::parse('2026-10-31 02:00', 'UTC'));
        $dashboard->call('$refresh')->assertSee('Llega hoy');
        $this->travelTo(Carbon::parse('2026-10-31 04:00', 'UTC'));
        $dashboard->call('$refresh')->assertSee('Embarque llegado')
            ->assertViewHas('upcomingArrivals', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('arrivedShipments', fn ($rows) => $rows->total() === 1);
        $this->assertSame(MscShipmentStatus::Navegando, $shipment->fresh()->status);
    }

    public function test_eta_notice_days_autosave_per_user_and_reject_invalid_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $dashboard = Livewire::test(Dashboard::class)->set('arrivalDays', 20)->assertHasNoErrors();
        $this->assertSame(20, $user->fresh()->msc_arrival_notice_days);
        Livewire::test(Dashboard::class)->assertSet('arrivalDays', 20);
        foreach ([-1, 366, '', 'abc', 1.5] as $invalid) {
            $dashboard->set('arrivalDays', $invalid)->assertHasErrors('arrivalDays');
            $this->assertSame(20, $user->fresh()->msc_arrival_notice_days);
        }
        $dashboard->set('arrivalDays', 0)->assertHasNoErrors();
        $this->actingAs(User::factory()->create());
        Livewire::test(Dashboard::class)->assertSet('arrivalDays', 10);
    }

    public function test_eta_statistics_require_shipment_access(): void
    {
        $this->actingAs(User::factory()->create(['access_international_trade' => false, 'access_foreign_trade' => false]));
        Livewire::test(Dashboard::class)->assertDontSee('Próximos embarques en llegar')
            ->assertViewHas('upcomingArrivals', null)->set('arrivalDays', 15)->assertForbidden();
    }

    public function test_dashboard_counts_unique_shipments_catalogs_and_unassigned_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        User::factory()->create();
        User::factory()->create(['is_active' => false]);
        $moto = Category::factory()->create(['name' => 'Moto']);
        $car = Category::factory()->create(['name' => 'Carro']);
        Category::factory()->create(['name' => 'Repuestos']);
        $product = Product::factory()->create(['category_id' => $moto->id]);
        $otherProduct = Product::factory()->create(['category_id' => $car->id]);
        $vessel = Vessel::factory()->create();
        Vessel::factory()->create();
        $shipment = MscShipment::factory()->create([
            'product_id' => $product->id, 'category_id' => $moto->id, 'vessel_id' => $vessel->id,
            'status' => MscShipmentStatus::Navegando, 'status_bl' => MscBillOfLadingStatus::Released,
        ]);
        $shipment->productLines()->createMany([
            ['product_id' => $product->id, 'container_qty' => 1],
            ['product_id' => $otherProduct->id, 'container_qty' => 1],
        ]);
        foreach ([
            [$moto->id, MscShipmentStatus::Navegando, MscBillOfLadingStatus::Released],
            [$car->id, MscShipmentStatus::Puerto, MscBillOfLadingStatus::PendingRelease],
            [null, null, null],
            [null, MscShipmentStatus::Draft, null],
        ] as [$category, $status, $bl]) {
            MscShipment::factory()->create(['product_id' => $product->id, 'vessel_id' => null, 'category_id' => $category, 'status' => $status, 'status_bl' => $bl]);
        }

        Livewire::test(Dashboard::class)->assertOk()
            ->assertViewHas('shipmentTotal', 5)->assertViewHas('productTotal', 2)
            ->assertViewHas('categoryTotal', 3)->assertViewHas('userTotal', 3)
            ->assertViewHas('activeUsers', 2)->assertViewHas('inactiveUsers', 1)
            ->assertViewHas('shipmentsWithoutVessel', 4)
            ->assertViewHas('vessels', fn ($rows) => $rows->count() === 2 && $rows->sum('msc_shipments_count') === 1)
            ->assertViewHas('shipmentStatuses', function ($rows): bool {
                $counts = array_column($rows, 'count', 'label');

                return array_sum($counts) === 5 && $counts['NAVEGANDO'] === 2 && $counts['Borrador'] === 2;
            })
            ->assertViewHas('blStatuses', fn ($rows) => array_sum(array_column($rows, 'count')) === 5 && array_column($rows, 'count', 'label')['Borrador'] === 2)
            ->assertViewHas('shipmentCategories', fn ($rows) => array_column($rows, 'count', 'label') === ['Carro' => 1, 'Moto' => 2, 'Repuestos' => 0, 'Sin categoría' => 2])
            ->assertViewHas('productCategories', fn ($rows) => array_column($rows, 'count', 'label') === ['Carro' => 1, 'Moto' => 1, 'Repuestos' => 0, 'Sin categoría' => 0])
            ->assertSee(route('foreign-trade.msc.index', ['status' => 'NAVEGANDO']), false)
            ->assertSee(route('foreign-trade.msc.index', ['category_id' => $moto->id]), false);
    }

    public function test_new_categories_and_products_appear_when_dashboard_refreshes(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $dashboard = Livewire::test(Dashboard::class)->assertViewHas('productTotal', 0);
        $category = Category::factory()->create(['name' => 'Nueva categoría']);
        $dashboard->call('$refresh')->assertSee('Nueva categoría')
            ->assertViewHas('productCategories', fn ($rows) => array_column($rows, 'count', 'label')['Nueva categoría'] === 0);
        Product::factory()->create(['category_id' => $category->id]);
        Product::factory()->create(['category_id' => null]);
        $dashboard->call('$refresh')->assertViewHas('productTotal', 2)
            ->assertViewHas('productCategories', fn ($rows) => array_column($rows, 'count', 'label') === ['Nueva categoría' => 1, 'Sin categoría' => 1]);
    }

    public function test_each_permission_combination_only_shows_authorized_statistics(): void
    {
        for ($mask = 0; $mask < 8; $mask++) {
            $international = (bool) ($mask & 1);
            $exterior = (bool) ($mask & 2);
            $configuration = (bool) ($mask & 4);
            $this->actingAs(User::factory()->create([
                'access_international_trade' => $international, 'access_foreign_trade' => $exterior, 'access_configuration' => $configuration,
            ]));
            $dashboard = Livewire::test(Dashboard::class)->assertOk()
                ->assertViewHas('hasOverview', $mask !== 0)
                ->assertViewHas('showTrade', $international || $exterior)
                ->assertViewHas('showExterior', $exterior)
                ->assertViewHas('showConfiguration', $configuration)
                ->assertViewHas('showUsers', false)
                ->assertDontSee('Usuarios registrados');
            $exterior ? $dashboard->assertSee('Status BL') : $dashboard->assertDontSee('Status BL');
            $configuration ? $dashboard->assertSee('Productos registrados') : $dashboard->assertDontSee('Productos registrados');
            if (! $mask) {
                $dashboard->assertSee('Bienvenido,')->assertDontSee('Panorama de embarques');
            }
        }
    }

    public function test_revoked_permissions_remove_statistics_on_livewire_refresh(): void
    {
        $user = User::factory()->create(['access_configuration' => true, 'access_international_trade' => true, 'access_foreign_trade' => true]);
        $this->actingAs($user);
        $dashboard = Livewire::test(Dashboard::class)->assertSee('Panorama de embarques');
        $user->access_configuration = $user->access_international_trade = $user->access_foreign_trade = false;
        $user->save();
        $dashboard->call('$refresh')->assertViewHas('hasOverview', false)->assertViewHas('shipmentTotal', 0)
            ->assertViewHas('productTotal', 0)->assertViewHas('userTotal', 0)->assertSee('Bienvenido,');
    }

    public function test_empty_catalogs_and_statuses_render_zeroes_without_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(Dashboard::class)->assertOk()->assertViewHas('shipmentTotal', 0)
            ->assertViewHas('productTotal', 0)->assertViewHas('userTotal', 1)
            ->assertSee('Todavía no hay registros')->assertSee('Todavía no hay buques registrados.');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('wire:poll.60s.keep-alive', false)
            ->assertDontSee('Actualizar datos')
            ->assertDontSee('Sesión activa · Vista general')
            ->assertDontSee('Bienvenido,')
            ->assertDontSee('Toda tu operación, de un vistazo.');
    }

    public function test_factory_totals_counts_and_new_factories_refresh_with_configuration_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $factory = Factory::factory()->create(['name' => 'Fábrica principal']);
        Factory::factory()->create(['name' => 'Fábrica sin embarques']);
        MscShipment::factory()->count(2)->create(['factory_id' => $factory->id]);
        MscShipment::factory()->create(['factory_id' => null]);
        $dashboard = Livewire::test(Dashboard::class)->assertSee('Fábricas registradas')
            ->assertSee('href="'.route('configuration.factories.edit', $factory).'"', false)
            ->assertSee('href="'.route('foreign-trade.msc.index', ['factory_id' => $factory->id]).'"', false)
            ->assertViewHas('factories', fn ($rows) => $rows->count() === 2 && $rows->sum('msc_shipments_count') === 2)
            ->assertViewHas('shipmentsWithoutFactory', 1);
        Factory::factory()->create(['name' => 'Fábrica nueva']);
        $dashboard->call('$refresh')->assertSee('Fábrica nueva')
            ->assertViewHas('factories', fn ($rows) => $rows->count() === 3);

        $user = User::factory()->create(['access_configuration' => false]);
        $this->actingAs($user);
        Livewire::test(Dashboard::class)->assertDontSee('Fábricas registradas')
            ->assertViewHas('factories', fn ($rows) => $rows->isEmpty());
        $user->access_configuration = true;
        $user->access_international_trade = $user->access_foreign_trade = false;
        $user->save();
        Livewire::test(Dashboard::class)->assertSee('Fábricas registradas')
            ->assertViewHas('factories', fn ($rows) => $rows->count() === 3 && ! isset($rows->first()->msc_shipments_count))
            ->assertDontSee('href="'.route('msc.index', ['factory_id' => $factory->id]).'"', false);
        $user->access_international_trade = true;
        $user->save();
        Livewire::test(Dashboard::class)
            ->assertSee('href="'.route('msc.index', ['factory_id' => $factory->id]).'"', false)
            ->assertDontSee('href="'.route('foreign-trade.msc.index', ['factory_id' => $factory->id]).'"', false);
    }
}
