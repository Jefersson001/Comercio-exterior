<?php

namespace Tests\Feature;

use App\Enums\MscBillOfLadingStatus;
use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Import;
use App\Livewire\Pages\Msc\Index;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vessel;
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

    private function foreignShipment(array $attributes = []): MscShipment
    {
        $categoryId = $attributes['category_id'] ?? Category::factory()->create()->id;
        $product = isset($attributes['product_id']) ? Product::findOrFail($attributes['product_id']) : Product::factory()->create();
        $product->update(['category_id' => $categoryId]);

        return MscShipment::factory()->create(array_replace(['category_id' => $categoryId, 'product_id' => $product->id], $attributes));
    }

    public function test_category_is_visible_only_in_foreign_trade_tree_and_form(): void
    {
        $category = Category::factory()->create(['name' => 'Categoría exclusiva']);
        $shipment = $this->foreignShipment(['category_id' => $category->id]);
        $this->get(route('msc.index'))->assertOk()->assertDontSee('Categoría exclusiva');
        $this->get(route('msc.edit', $shipment))->assertOk()->assertDontSee('id="category_id"', false);
        $this->get(route('foreign-trade.msc.index'))->assertOk()->assertSee('Categoría exclusiva')
            ->assertSee('data-href="'.route('foreign-trade.msc.edit', $shipment).'"', false)
            ->assertSee('href="'.route('foreign-trade.msc.create').'"', false);
        $this->get(route('foreign-trade.msc.edit', $shipment))->assertOk()->assertSee('id="category_id"', false)
            ->assertSee('data-foreign-trade-notebook', false)
            ->assertSee('role="tab"', false)
            ->assertSee('Fase 1')->assertSee('Fase 2')->assertSee('Fase 3')->assertDontSee('Fase 4')->assertSee('Notas')
            ->assertSee('data-foreign-trade-panel="phase-1"', false)
            ->assertSeeInOrder(['N°EXP.', 'DUA/INTR', 'SEABRIDGE', 'SENCAMER', 'COMEX', 'Fecha Recepción', 'Fecha Reconocimiento', 'Contenedor', 'Año', 'ETD', 'ETA VNZL', 'Puerto', 'TRASEGADO', 'In bond', 'Días libres', 'Demora', 'Abandono legal'])
            ->assertSee('Categoría exclusiva')->assertSee('href="'.route('foreign-trade.msc.index').'"', false);
        $this->get(route('msc.edit', $shipment))->assertDontSee('data-foreign-trade-notebook', false);
        $this->assertDatabaseCount('msc_shipments', 1);
    }

    public function test_foreign_trade_notebook_keeps_product_category_visible_across_tabs(): void
    {
        $category = Category::factory()->create();
        $shipment = $this->foreignShipment(['category_id' => $category->id]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('activeForeignTradeTab', 'phase-1')
            ->assertSee('data-foreign-trade-panel="phase-1"', false)
            ->assertSee('id="category_id"', false)
            ->call('selectForeignTradeTab', 'phase-2')
            ->assertSet('activeForeignTradeTab', 'phase-2')
            ->assertSee('data-foreign-trade-panel="phase-2"', false)
            ->assertSeeInOrder(['name=\"registration_date\"', 'name=\"phase_two_recognition_date\"', 'name=\"validation_date\"', 'name=\"plant_receipt_date\"', 'name=\"location\"', 'name=\"return_date\"'], false)
            ->assertSee('id="category_id"', false)
            ->call('selectForeignTradeTab', 'phase-3')
            ->assertSet('activeForeignTradeTab', 'phase-3')
            ->assertSee('data-foreign-trade-panel="phase-3"', false)
            ->assertSeeInOrder(['name=\"original_docs_receipt_date\"', 'name=\"aa_instructions_sent_date\"', 'name=\"bl_release_date\"', 'name=\"sencamer_request_date\"', 'name=\"sencamer_receipt_date\"', 'name=\"comex_request_date\"', 'name=\"comex_receipt_date\"'], false)
            ->assertSee('id="category_id"', false)
            ->call('selectForeignTradeTab', 'notes')
            ->assertSee('data-foreign-trade-panel="notes"', false)
            ->assertSee('STATUS AGENTE DE ADUANA')
            ->assertSee('OBSERVACION AGENTE DE ADUANA')
            ->assertSee('<textarea id="customs_agent_status"', false)
            ->assertSee('<textarea id="customs_agent_observation"', false)
            ->assertDontSee('Fase 4')
            ->assertSee('id="category_id"', false);
    }

    public function test_customs_agent_notes_save_reload_and_validate(): void
    {
        $shipment = $this->foreignShipment();
        $status = str_repeat('Estado revisado.', 30);
        $observation = 'Pendiente de confirmación del agente.';

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'notes')
            ->set('customs_agent_status', $status)
            ->set('customs_agent_observation', $observation)
            ->call('save')
            ->assertHasNoErrors();

        $shipment->refresh();
        $this->assertSame($status, $shipment->customs_agent_status);
        $this->assertSame($observation, $shipment->customs_agent_observation);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'notes')
            ->assertSet('customs_agent_status', $status)
            ->assertSet('customs_agent_observation', $observation);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('customs_agent_status', str_repeat('A', 10001))
            ->set('customs_agent_observation', str_repeat('A', 10001))
            ->call('save')
            ->assertHasErrors(['customs_agent_status', 'customs_agent_observation']);
    }

    public function test_foreign_trade_phase_one_saves_details_and_multiple_container_lines(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        Livewire::test(Form::class, ['foreignTrade' => true])
            ->set('etd', '2026-10-01')
            ->set('eta', '2026-11-01')
            ->set('bl_master', 'PHASE-ONE-BL')
            ->set('productLines', [['product_id' => $product->id, 'container_qty' => '2', 'units_qty' => '']])
            ->set('free_time', '21')
            ->set('exp_number', 'EXP-2026-A1')
            ->set('dua_intr', 'DUA/INT-82B')
            ->set('seabridge', 'SEA-001')
            ->set('sencamer', 'SEN-03C')
            ->set('comex', 'COMEX-9')
            ->set('recognition_date', '2026-10-18')
            ->set('receipt_date', '2026-10-19')
            ->set('category_id', $category->id)
            ->call('addContainerLine')
            ->set('containerLines.0.name', 'MSCU1234567')
            ->call('addContainerLine')
            ->set('containerLines.1.name', 'TGHU7654321')
            ->set('shipment_year', '2026')
            ->set('etd_vnzl', '2026-10-20')
            ->set('eta_vnzl', '2026-10-21')
            ->set('port', 'Puerto Cabello')
            ->set('trasegado', 'TRS-09A')
            ->set('in_bond_date', '2026-10-22')
            ->set('free_days', '15')
            ->set('delay_date', '2026-10-23')
            ->set('legal_abandonment_date', '2026-10-24')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('foreign-trade.msc.index'));

        $shipment = MscShipment::where('exp_number', 'EXP-2026-A1')->firstOrFail();
        $this->assertSame('DUA/INT-82B', $shipment->dua_intr);
        $this->assertSame('SEA-001', $shipment->seabridge);
        $this->assertSame('SEN-03C', $shipment->sencamer);
        $this->assertSame('COMEX-9', $shipment->comex);
        $this->assertSame('2026-10-18', $shipment->recognition_date?->format('Y-m-d'));
        $this->assertSame('2026-10-19', $shipment->receipt_date?->format('Y-m-d'));
        $this->assertSame('2026', $shipment->shipment_year);
        $this->assertSame('2026-10-20', $shipment->etd_vnzl?->format('Y-m-d'));
        $this->assertSame('2026-10-21', $shipment->eta_vnzl?->format('Y-m-d'));
        $this->assertSame('Puerto Cabello', $shipment->port);
        $this->assertSame('TRS-09A', $shipment->trasegado);
        $this->assertSame('2026-10-22', $shipment->in_bond_date?->format('Y-m-d'));
        $this->assertSame(15, $shipment->free_days);
        $this->assertSame('2026-10-23', $shipment->delay_date?->format('Y-m-d'));
        $this->assertSame('2026-10-24', $shipment->legal_abandonment_date?->format('Y-m-d'));
        $this->assertSame($category->id, $shipment->category_id);
        $this->assertSame(['MSCU1234567', 'TGHU7654321'], $shipment->containers->pluck('name')->all());
    }

    public function test_foreign_trade_phase_two_saves_and_reloads_its_dates_and_location(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'phase-2')
            ->set('registration_date', '2026-10-25')
            ->set('phase_two_recognition_date', '2026-10-26')
            ->set('validation_date', '2026-10-27')
            ->set('plant_receipt_date', '2026-10-28')
            ->set('location', 'Almacén principal')
            ->set('return_date', '2026-10-29')
            ->call('save')
            ->assertHasNoErrors();

        $shipment->refresh();
        $this->assertSame('2026-10-25', $shipment->registration_date?->format('Y-m-d'));
        $this->assertSame('2026-10-26', $shipment->phase_two_recognition_date?->format('Y-m-d'));
        $this->assertSame('2026-10-27', $shipment->validation_date?->format('Y-m-d'));
        $this->assertSame('2026-10-28', $shipment->plant_receipt_date?->format('Y-m-d'));
        $this->assertSame('Almacén principal', $shipment->location);
        $this->assertSame('2026-10-29', $shipment->return_date?->format('Y-m-d'));

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'phase-2')
            ->assertSet('registration_date', '2026-10-25')
            ->assertSet('phase_two_recognition_date', '2026-10-26')
            ->assertSet('validation_date', '2026-10-27')
            ->assertSet('plant_receipt_date', '2026-10-28')
            ->assertSet('location', 'Almacén principal')
            ->assertSet('return_date', '2026-10-29');
    }

    public function test_foreign_trade_phase_three_saves_and_reloads_all_dates(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'phase-3')
            ->set('original_docs_receipt_date', '2026-10-30')
            ->set('aa_instructions_sent_date', '2026-10-31')
            ->set('bl_release_date', '2026-11-01')
            ->set('sencamer_request_date', '2026-11-02')
            ->set('sencamer_receipt_date', '2026-11-03')
            ->set('comex_request_date', '2026-11-04')
            ->set('comex_receipt_date', '2026-11-05')
            ->call('save')
            ->assertHasNoErrors();

        $shipment->refresh();
        $this->assertSame('2026-10-30', $shipment->original_docs_receipt_date?->format('Y-m-d'));
        $this->assertSame('2026-10-31', $shipment->aa_instructions_sent_date?->format('Y-m-d'));
        $this->assertSame('2026-11-01', $shipment->bl_release_date?->format('Y-m-d'));
        $this->assertSame('2026-11-02', $shipment->sencamer_request_date?->format('Y-m-d'));
        $this->assertSame('2026-11-03', $shipment->sencamer_receipt_date?->format('Y-m-d'));
        $this->assertSame('2026-11-04', $shipment->comex_request_date?->format('Y-m-d'));
        $this->assertSame('2026-11-05', $shipment->comex_receipt_date?->format('Y-m-d'));

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'phase-3')
            ->assertSet('original_docs_receipt_date', '2026-10-30')
            ->assertSet('aa_instructions_sent_date', '2026-10-31')
            ->assertSet('bl_release_date', '2026-11-01')
            ->assertSet('sencamer_request_date', '2026-11-02')
            ->assertSet('sencamer_receipt_date', '2026-11-03')
            ->assertSet('comex_request_date', '2026-11-04')
            ->assertSet('comex_receipt_date', '2026-11-05');
    }

    public function test_foreign_trade_phase_one_can_edit_and_remove_container_lines(): void
    {
        $shipment = $this->foreignShipment();
        $shipment->containers()->createMany([
            ['name' => 'CONT-ONE'],
            ['name' => 'CONT-TWO'],
        ]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('containerLines', [['name' => 'CONT-ONE'], ['name' => 'CONT-TWO']])
            ->call('removeContainerLine', 0)
            ->assertSet('containerLines', [['name' => 'CONT-TWO']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['CONT-TWO'], $shipment->fresh()->containers->pluck('name')->all());
    }

    public function test_foreign_trade_phase_one_saves_and_reloads_product_variant_lines(): void
    {
        $product = Product::factory()->create(['name' => 'Camisa']);
        $red = ProductVariant::create(['product_id' => $product->id, 'color' => 'Rojo']);
        $blue = ProductVariant::create(['product_id' => $product->id, 'color' => 'Azul']);
        $shipment = $this->foreignShipment(['product_id' => $product->id]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('addProductVariantLine')
            ->assertSee('Camisa (Rojo)')
            ->assertSee('Camisa (Azul)')
            ->set('productVariantLines.0.product_variant_id', $red->id)
            ->set('productVariantLines.0.quantity', '4')
            ->call('addProductVariantLine')
            ->set('productVariantLines.1.product_variant_id', $blue->id)
            ->set('productVariantLines.1.quantity', '')
            ->call('addProductVariantLine')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            [
                ['product_variant_id' => $red->id, 'quantity' => 4],
                ['product_variant_id' => $blue->id, 'quantity' => 0],
            ],
            $shipment->fresh()->productVariantLines
                ->map(fn ($line): array => ['product_variant_id' => $line->product_variant_id, 'quantity' => $line->quantity])
                ->all(),
        );

        Livewire::test(Form::class, ['shipment' => $shipment->fresh(), 'foreignTrade' => true])
            ->assertSet('productVariantLines', [
                ['product_variant_id' => $red->id, 'quantity' => '4'],
                ['product_variant_id' => $blue->id, 'quantity' => '0'],
            ]);
    }

    public function test_foreign_trade_variant_lines_only_allow_variants_of_selected_product(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $variant = ProductVariant::create(['product_id' => $otherProduct->id, 'color' => 'Rojo']);
        $shipment = $this->foreignShipment(['product_id' => $product->id]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('addProductVariantLine')
            ->set('productVariantLines.0.product_variant_id', $variant->id)
            ->set('productVariantLines.0.quantity', '1')
            ->call('save')
            ->assertHasErrors('productVariantLines.0.product_variant_id');

        $this->assertDatabaseCount('msc_shipment_product_variants', 0);
    }

    public function test_changing_product_clears_selected_variant_lines(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Rojo']);
        $otherProduct = Product::factory()->create();
        $shipment = $this->foreignShipment(['product_id' => $product->id]);
        $shipment->productVariantLines()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);
        $otherProduct->update(['category_id' => $shipment->category_id]);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('productLines.0.product_id', $otherProduct->id)
            ->assertSet('productVariantLines', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(0, $shipment->fresh()->productVariantLines);
    }

    public function test_international_form_does_not_display_or_overwrite_variant_lines(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Rojo']);
        $shipment = $this->foreignShipment(['product_id' => $product->id]);
        $shipment->productVariantLines()->create(['product_variant_id' => $variant->id, 'quantity' => 7]);

        $this->get(route('msc.edit', $shipment))->assertDontSee('Variante del producto');

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('productVariantLines', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7, $shipment->fresh()->productVariantLines->sole()->quantity);
    }

    public function test_international_form_does_not_expose_or_overwrite_foreign_trade_phase_one_data(): void
    {
        $shipment = $this->foreignShipment([
            'exp_number' => 'FOREIGN-EXP',
            'recognition_date' => '2026-10-18',
            'shipment_year' => '2026',
            'etd_vnzl' => '2026-10-20',
            'free_days' => 15,
            'registration_date' => '2026-10-25',
            'phase_two_recognition_date' => '2026-10-26',
            'validation_date' => '2026-10-27',
            'plant_receipt_date' => '2026-10-28',
            'location' => 'Almacén exterior',
            'return_date' => '2026-10-29',
            'original_docs_receipt_date' => '2026-10-30',
            'aa_instructions_sent_date' => '2026-10-31',
            'bl_release_date' => '2026-11-01',
            'sencamer_request_date' => '2026-11-02',
            'sencamer_receipt_date' => '2026-11-03',
            'comex_request_date' => '2026-11-04',
            'comex_receipt_date' => '2026-11-05',
            'customs_agent_status' => 'En revisión',
            'customs_agent_observation' => 'Dato reservado para Comercio Exterior.',
        ]);
        $shipment->containers()->create(['name' => 'FOREIGN-CONT']);

        $this->get(route('msc.edit', $shipment))
            ->assertDontSee('N°EXP.')
            ->assertDontSee('CONTENEDOR')
            ->assertDontSee('ETA VNZL')
            ->assertDontSee('name=\"registration_date\"', false)
            ->assertDontSee('name=\"phase_two_recognition_date\"', false)
            ->assertDontSee('name=\"validation_date\"', false)
            ->assertDontSee('name=\"plant_receipt_date\"', false)
            ->assertDontSee('name=\"location\"', false)
            ->assertDontSee('name=\"return_date\"', false)
            ->assertDontSee('name=\"original_docs_receipt_date\"', false)
            ->assertDontSee('name=\"aa_instructions_sent_date\"', false)
            ->assertDontSee('name=\"bl_release_date\"', false)
            ->assertDontSee('name=\"sencamer_request_date\"', false)
            ->assertDontSee('name=\"sencamer_receipt_date\"', false)
            ->assertDontSee('name=\"comex_request_date\"', false)
            ->assertDontSee('name=\"comex_receipt_date\"', false)
            ->assertDontSee('STATUS AGENTE DE ADUANA')
            ->assertDontSee('OBSERVACION AGENTE DE ADUANA')
            ->assertDontSee('id="category_id"', false);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->set('released', 'International update')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('FOREIGN-EXP', $shipment->fresh()->exp_number);
        $this->assertSame('2026-10-18', $shipment->fresh()->recognition_date?->format('Y-m-d'));
        $this->assertSame('2026', $shipment->fresh()->shipment_year);
        $this->assertSame('2026-10-20', $shipment->fresh()->etd_vnzl?->format('Y-m-d'));
        $this->assertSame(15, $shipment->fresh()->free_days);
        $this->assertSame(['FOREIGN-CONT'], $shipment->fresh()->containers->pluck('name')->all());
        $this->assertSame('2026-10-25', $shipment->fresh()->registration_date?->format('Y-m-d'));
        $this->assertSame('2026-10-26', $shipment->fresh()->phase_two_recognition_date?->format('Y-m-d'));
        $this->assertSame('2026-10-27', $shipment->fresh()->validation_date?->format('Y-m-d'));
        $this->assertSame('2026-10-28', $shipment->fresh()->plant_receipt_date?->format('Y-m-d'));
        $this->assertSame('Almacén exterior', $shipment->fresh()->location);
        $this->assertSame('2026-10-29', $shipment->fresh()->return_date?->format('Y-m-d'));
        $this->assertSame('2026-10-30', $shipment->fresh()->original_docs_receipt_date?->format('Y-m-d'));
        $this->assertSame('2026-10-31', $shipment->fresh()->aa_instructions_sent_date?->format('Y-m-d'));
        $this->assertSame('2026-11-01', $shipment->fresh()->bl_release_date?->format('Y-m-d'));
        $this->assertSame('2026-11-02', $shipment->fresh()->sencamer_request_date?->format('Y-m-d'));
        $this->assertSame('2026-11-03', $shipment->fresh()->sencamer_receipt_date?->format('Y-m-d'));
        $this->assertSame('2026-11-04', $shipment->fresh()->comex_request_date?->format('Y-m-d'));
        $this->assertSame('2026-11-05', $shipment->fresh()->comex_receipt_date?->format('Y-m-d'));
        $this->assertSame('En revisión', $shipment->fresh()->customs_agent_status);
        $this->assertSame('Dato reservado para Comercio Exterior.', $shipment->fresh()->customs_agent_observation);
    }

    public function test_foreign_trade_phase_three_validates_every_field_as_a_date(): void
    {
        $shipment = $this->foreignShipment();
        $fields = [
            'original_docs_receipt_date',
            'aa_instructions_sent_date',
            'bl_release_date',
            'sencamer_request_date',
            'sencamer_receipt_date',
            'comex_request_date',
            'comex_receipt_date',
        ];

        $component = Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true]);
        foreach ($fields as $field) {
            $component->set($field, 'not-a-date');
        }
        $component->call('save')->assertHasErrors($fields);
    }

    public function test_foreign_trade_phase_two_validates_date_fields_and_location_length(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('registration_date', 'invalid-date')
            ->set('phase_two_recognition_date', 'invalid-date')
            ->set('validation_date', 'invalid-date')
            ->set('plant_receipt_date', 'invalid-date')
            ->set('location', str_repeat('A', 256))
            ->set('return_date', 'invalid-date')
            ->call('save')
            ->assertHasErrors([
                'registration_date',
                'phase_two_recognition_date',
                'validation_date',
                'plant_receipt_date',
                'location',
                'return_date',
            ]);
    }

    public function test_foreign_trade_phase_one_validates_year_dates_and_free_days(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('shipment_year', '20A6')
            ->set('etd_vnzl', 'not-a-date')
            ->set('free_days', '-1')
            ->call('save')
            ->assertHasErrors(['shipment_year', 'etd_vnzl', 'free_days']);
    }

    public function test_foreign_trade_requires_a_valid_category_matching_the_product(): void
    {
        $category = Category::factory()->create();
        $shipment = $this->foreignShipment();
        $shipment->product->update(['category_id' => $category->id]);
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', $category->id)->call('save')->assertHasNoErrors()->assertNoRedirect();
        $this->assertSame($category->id, $shipment->fresh()->category_id);
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', 999999)->call('save')->assertHasErrors('category_id');
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('category_id', null)->call('save')->assertHasErrors('category_id');
        $this->assertSame($category->id, $shipment->fresh()->category_id);
    }

    public function test_status_bl_is_selectable_and_visible_only_in_foreign_trade(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->assertSet('status_bl', MscBillOfLadingStatus::Draft->value)
            ->set('status_bl', MscBillOfLadingStatus::PendingRelease->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(MscBillOfLadingStatus::PendingRelease, $shipment->fresh()->status_bl);

        $this->get(route('foreign-trade.msc.edit', $shipment))
            ->assertSee('Status BL')
            ->assertSee('BL LIBERADO OK')
            ->assertSee('POR LIBERACION BL')
            ->assertSee('POR LIBERACION BLSIN DOCUMENTO');
        $this->get(route('msc.edit', $shipment))->assertDontSee('Status BL');

        $this->get(route('foreign-trade.msc.index'))
            ->assertSee('Status BL')
            ->assertSee('POR LIBERACION BL');
        $this->get(route('msc.index'))->assertDontSee('Status BL');
    }

    public function test_international_updates_preserve_status_bl_and_exterior_rejects_unknown_values(): void
    {
        $shipment = $this->foreignShipment(['status_bl' => MscBillOfLadingStatus::Released]);

        Livewire::test(Form::class, ['shipment' => $shipment])
            ->assertSet('status_bl', MscBillOfLadingStatus::Draft->value)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(MscBillOfLadingStatus::Released, $shipment->fresh()->status_bl);

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->set('status_bl', 'INVALID')
            ->call('save')
            ->assertHasErrors('status_bl');
        $this->assertSame(MscBillOfLadingStatus::Released, $shipment->fresh()->status_bl);
    }

    public function test_international_updates_preserve_the_category_and_ignore_hidden_field_changes(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $factory = Factory::factory()->create();
        $shipment = $this->foreignShipment(['category_id' => $category->id, 'factory_id' => $factory->id]);
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
        $shipment = $this->foreignShipment(['category_id' => $category->id]);
        Livewire::test(Index::class, ['foreignTrade' => true])->set('search', 'Specific Category')
            ->assertSet('foreignTrade', true)->assertSee($shipment->bl_master)
            ->assertSee('href="'.route('foreign-trade.msc.import').'"', false);
        Livewire::test(Index::class)->set('search', 'Specific Category')->assertDontSee($shipment->bl_master);
    }

    private function downloadRows(string $route, ?string $sheetName = null): array
    {
        $response = $this->get(route($route))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetName !== null && $sheet->getName() !== $sheetName) {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_values($row->toArray());
                }
                if ($sheetName === null || $sheet->getName() === $sheetName) {
                    break;
                }
            }
        } finally {
            $reader->close();
            unset($reader, $sheet, $row);
            unlink($path);
        }

        return $rows;
    }

    public function test_template_has_context_tabs_and_reports_use_their_context_columns(): void
    {
        $category = Category::factory()->create(['name' => 'Spare parts']);
        $this->foreignShipment(['category_id' => $category->id]);
        $internationalTemplate = $this->downloadRows('msc.template', 'Comercio Internacional');
        $foreignTradeTemplate = $this->downloadRows('msc.template', 'Comercio Exterior');
        $internationalReport = $this->downloadRows('msc.export');
        $foreignTradeReport = $this->downloadRows('foreign-trade.msc.export');

        $this->assertCount(13, $internationalTemplate[0]);
        $this->assertCount(42, $foreignTradeTemplate[0]);
        $this->assertSame('N°EXP.', $foreignTradeTemplate[0][0]);
        $this->assertCount(13, $internationalReport[0]);
        $this->assertCount(13, $internationalReport[1]);
        $this->assertSame('TIPO', $foreignTradeReport[0][11]);
        $this->assertSame('Spare parts', $foreignTradeReport[1][11]);
        $this->assertCount(42, $foreignTradeReport[1]);
    }

    private function spreadsheet(string $bill, string $category): UploadedFile
    {
        Factory::firstOrCreate(['name' => 'Factory']);
        Vessel::firstOrCreate(['name' => 'Agent']);
        $path = tempnam(sys_get_temp_dir(), 'test-msc-category-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values(MscFields::headers(true))));
        $writer->addRow(Row::fromValues([99, '2026-10-01', '2026-11-01', $bill, '', 'Factory', 'Model One', 1, '', 21, 'Agent', '', '', $category]));
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('msc.xlsx', file_get_contents($path));
        unlink($path);

        return $file;
    }

    public function test_exterior_import_resolves_category_and_international_import_ignores_it(): void
    {
        $product = Product::factory()->create(['name' => 'Model One']);
        $category = Category::factory()->create(['name' => 'Parts']);
        $product->update(['category_id' => $category->id]);
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
