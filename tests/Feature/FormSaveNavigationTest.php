<?php

namespace Tests\Feature;

use App\Livewire\Pages\Msc\Form;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vessel;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormSaveNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_catalogs_and_users_keeps_the_form_open(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        foreach ([
            [Category::class, 'Categories', 'category'],
            [Factory::class, 'Factories', 'factory'],
            [Vessel::class, 'Vessels', 'vessel'],
            [Product::class, 'Products', 'product'],
            [User::class, 'Users', 'user'],
        ] as [$model, $module, $parameter]) {
            $record = $model::factory()->create($model === User::class ? ['last_name' => 'Prueba'] : []);
            Livewire::test('App\\Livewire\\Pages\\Configuration\\'.$module.'\\Form', [$parameter => $record])
                ->set('name', 'Nombre actualizado')
                ->call('save')->assertHasNoErrors()->assertNoRedirect()
                ->assertDispatched('record-saved')->assertSee(session('status'))
                ->set('name', 'Segunda modificación')
                ->call('save')->assertHasNoErrors()->assertNoRedirect();
            $this->assertSame('Segunda modificación', $record->fresh()->name);
        }
    }

    public function test_saving_product_variants_twice_keeps_their_identity(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $product = Product::factory()->create();
        $form = Livewire::test(\App\Livewire\Pages\Configuration\Products\Form::class, ['product' => $product])
            ->call('addVariant')->set('variants.0.color', 'Rojo')->call('save')->assertHasNoErrors()->assertNoRedirect();
        $variant = $product->variants()->sole();
        $form->assertSet('variants.0.id', $variant->id)
            ->set('variants.0.color', 'Azul')->call('save')->assertHasNoErrors()->assertNoRedirect();
        $this->assertSame($variant->id, $product->variants()->sole()->id);
        $this->assertSame('Azul', $variant->fresh()->color);
    }

    public function test_msc_updates_stay_in_the_form_in_both_contexts(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        foreach ([false, true] as $foreignTrade) {
            $shipment = MscShipment::factory()->create(['product_id' => $product->id, 'category_id' => $category->id]);
            Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => $foreignTrade])
                ->set('bl_master', 'BL actualizado '.$shipment->id)->call('save')->assertHasNoErrors()->assertNoRedirect()
                ->assertDispatched('record-saved')->assertSee(session('status'))
                ->set('bl_master', 'Segundo BL '.$shipment->id)->call('save')->assertHasNoErrors()->assertNoRedirect();
            $this->assertSame('Segundo BL '.$shipment->id, $shipment->fresh()->bl_master);
        }
    }
}
