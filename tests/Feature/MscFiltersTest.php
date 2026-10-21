<?php

namespace Tests\Feature;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Livewire\Pages\Msc\Index;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class MscFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_filter_combines_with_status_and_applies_to_both_contexts_and_reports(): void
    {
        $this->actingAs(User::factory()->create());
        $factory = Factory::factory()->create();
        $other = Factory::factory()->create();
        $matching = MscShipment::factory()->create(['factory_id' => $factory->id, 'status' => MscShipmentStatus::Puerto]);
        $otherFactory = MscShipment::factory()->create(['factory_id' => $other->id, 'status' => MscShipmentStatus::Puerto]);
        $otherStatus = MscShipment::factory()->create(['factory_id' => $factory->id, 'status' => MscShipmentStatus::Navegando]);

        foreach ([false, true] as $foreignTrade) {
            $prefix = $foreignTrade ? 'foreign-trade.msc.' : 'msc.';
            $component = Livewire::test(Index::class, ['foreignTrade' => $foreignTrade])
                ->assertSee('id="msc-factory"', false)
                ->set('factory_id', (string) $factory->id)
                ->assertSee($matching->bl_master)->assertSee($otherStatus->bl_master)->assertDontSee($otherFactory->bl_master)
                ->set('status', MscShipmentStatus::Puerto->value)->assertDontSee($otherStatus->bl_master);
            foreach (['export', 'export-direct'] as $action) {
                $parameters = ['search' => '', 'foreign_trade' => $foreignTrade, 'status' => MscShipmentStatus::Puerto->value, 'factory_id' => (string) $factory->id];
                $link = 'href="'.htmlspecialchars(route($prefix.$action, $parameters), ENT_QUOTES).'"';
                if ($foreignTrade || $action !== 'export-direct') {
                    $component->assertSee($link, false);
                } else {
                    $component->assertDontSee($link, false);
                }
                $response = $this->get(route($prefix.$action, $parameters))->assertOk();
                $path = $response->baseResponse->getFile()->getPathname();
                $reader = new Reader;
                $values = [];
                try {
                    $reader->open($path);
                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            array_push($values, ...$row->toArray());
                        }
                    }
                } finally {
                    $reader->close();
                    unlink($path);
                }
                $this->assertContains($matching->bl_master, $values);
                $this->assertNotContains($otherFactory->bl_master, $values);
                $this->assertNotContains($otherStatus->bl_master, $values);
            }
            $this->get(route($prefix.'index', ['factory_id' => $factory->id]))->assertOk()
                ->assertSee($matching->bl_master)->assertDontSee($otherFactory->bl_master);
            $component->call('gotoPage', 2)->set('factory_id', (string) $other->id)->assertSet('paginators.page', 1)
                ->call('clearFilters')->assertSet('factory_id', '')->assertSet('status', '')
                ->assertSee($matching->bl_master)->assertSee($otherFactory->bl_master);
        }
    }

    public function test_foreign_trade_filters_combine_with_search_and_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $matching = MscShipment::factory()->create([
            'bl_master' => 'FILTER-MATCH', 'status' => MscShipmentStatus::Puerto,
            'status_bl' => MscBillOfLadingStatus::Released, 'category_id' => $category->id,
        ]);
        $differentStatus = MscShipment::factory()->create([
            'bl_master' => 'FILTER-STATUS', 'status' => MscShipmentStatus::Navegando,
            'status_bl' => MscBillOfLadingStatus::Released, 'category_id' => $category->id,
        ]);
        $differentBl = MscShipment::factory()->create([
            'bl_master' => 'FILTER-BL', 'status' => MscShipmentStatus::Puerto,
            'status_bl' => MscBillOfLadingStatus::PendingRelease, 'category_id' => $category->id,
        ]);
        $differentCategory = MscShipment::factory()->create([
            'bl_master' => 'FILTER-CATEGORY', 'status' => MscShipmentStatus::Puerto,
            'status_bl' => MscBillOfLadingStatus::Released, 'category_id' => null,
        ]);

        Livewire::test(Index::class, ['foreignTrade' => true])
            ->assertSee('id="msc-status"', false)
            ->set('status', MscShipmentStatus::Puerto->value)
            ->assertSee($matching->bl_master)->assertDontSee($differentStatus->bl_master)
            ->set('status_bl', MscBillOfLadingStatus::Released->value)
            ->assertDontSee($differentBl->bl_master)
            ->set('category_id', (string) $category->id)
            ->assertSee($matching->bl_master)->assertDontSee($differentCategory->bl_master)
            ->set('search', 'NO-MATCH')->assertDontSee($matching->bl_master)
            ->set('search', 'FILTER')
            ->call('gotoPage', 2)
            ->set('status', MscShipmentStatus::Navegando->value)
            ->assertSet('paginators.page', 1)
            ->assertSee($differentStatus->bl_master)
            ->call('clearFilters')
            ->assertSet('status', '')->assertSet('status_bl', '')->assertSet('category_id', '')
            ->assertSee($matching->bl_master)->assertSee($differentStatus->bl_master)
            ->assertSee($differentBl->bl_master)->assertSee($differentCategory->bl_master);

        Livewire::test(Index::class)
            ->set('status', MscShipmentStatus::Puerto->value)
            ->assertSee('id="msc-status"', false)
            ->assertDontSee('id="msc-status-bl"', false)
            ->assertDontSee('id="msc-category"', false)
            ->assertSee($matching->bl_master)
            ->assertDontSee($differentStatus->bl_master)
            ->call('clearFilters')
            ->assertSee($differentStatus->bl_master);

        $this->get(route('foreign-trade.msc.index', [
            'status' => MscShipmentStatus::Navegando->value,
            'status_bl' => MscBillOfLadingStatus::Released->value,
            'category_id' => $category->id,
        ]))->assertOk()->assertSee($differentStatus->bl_master)->assertDontSee($matching->bl_master);
    }

    public function test_both_reports_export_only_shipments_matching_all_filters(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $attributes = [
            'status' => MscShipmentStatus::Puerto,
            'status_bl' => MscBillOfLadingStatus::Released,
            'category_id' => $category->id,
        ];
        $matching = MscShipment::factory()->create($attributes + ['bl_master' => 'EXPORT-MATCH']);
        $navigating = MscShipment::factory()->create(array_replace($attributes, ['status' => MscShipmentStatus::Navegando]));
        MscShipment::factory()->create(array_replace($attributes, ['status_bl' => MscBillOfLadingStatus::PendingRelease]));
        MscShipment::factory()->create(array_replace($attributes, ['category_id' => null]));
        MscShipment::factory()->create($attributes + ['bl_master' => 'OTHER-SEARCH']);
        $parameters = [
            'search' => 'EXPORT', 'foreign_trade' => true,
            'status' => MscShipmentStatus::Puerto->value,
            'status_bl' => MscBillOfLadingStatus::Released->value,
            'category_id' => $category->id,
        ];

        $component = Livewire::test(Index::class, ['foreignTrade' => true])
            ->set('search', 'EXPORT')->set('status', $parameters['status'])
            ->set('status_bl', $parameters['status_bl'])->set('category_id', (string) $category->id);

        foreach ([
            ['foreign-trade.msc.export', $parameters, $matching],
            ['foreign-trade.msc.export-direct', $parameters, $matching],
            ['msc.export', ['status' => MscShipmentStatus::Navegando->value], $navigating],
            ['msc.export-direct', ['status' => MscShipmentStatus::Navegando->value], $navigating],
        ] as [$route, $filters, $expected]) {
            if (! isset($filters['foreign_trade'])) {
                $component = Livewire::test(Index::class)->set('status', $filters['status']);
                $filters = ['search' => '', 'foreign_trade' => false, ...$filters];
            }
            $link = 'href="'.htmlspecialchars(route($route, $filters), ENT_QUOTES).'"';
            if ($route === 'msc.export-direct') {
                $component->assertDontSee($link, false);
            } else {
                $component->assertSee($link, false);
            }
            $response = $this->get(route($route, $filters))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $reader = new Reader;
            $rows = [];
            try {
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $rows[] = $row->toArray();
                    }
                }
            } finally {
                $reader->close();
                unlink($path);
            }
            $this->assertCount(2, $rows);
            $this->assertContains($expected->bl_master, $rows[1]);
        }
    }
}
