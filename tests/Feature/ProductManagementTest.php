<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Products\Form;
use App\Livewire\Pages\Configuration\Products\Index;
use App\Models\Product;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['role' => UserRole::Administrator]);
    }

    public function test_product_information_is_limited_to_name(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'name', 'units_per_container', 'created_at', 'updated_at'], Schema::getColumnListing('products'));
    }

    public function test_products_require_an_authenticated_administrator(): void
    {
        $product = Product::factory()->create();
        $urls = [route('configuration.products.index'), route('configuration.products.create'), route('configuration.products.edit', $product)];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }

        Livewire::test(Index::class)->assertForbidden();
        Livewire::test(Form::class)->assertForbidden();
    }

    public function test_administrator_can_open_tree_create_and_edit_views(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create(['name' => 'Producto de prueba']);
        $editUrl = route('configuration.products.edit', $product);
        $this->get(route('configuration.products.index'))->assertOk()->assertSee($product->name)
            ->assertSee('data-href="'.$editUrl.'"', false)->assertSee('href="'.$editUrl.'"', false);
        $this->get(route('configuration.products.create'))->assertOk()->assertSee('Nombre *')->assertDontSee('Eliminar producto');
        $this->get($editUrl)->assertOk()->assertSee('Guardar cambios')->assertSee('Eliminar producto');
        $this->get(route('dashboard'))->assertSee('href="'.route('configuration.products.index').'"', false);

        Livewire::test(Form::class, ['product' => $product])->assertSet('productId', $product->id)->assertSet('name', $product->name);
    }

    public function test_administrator_can_create_a_product_and_name_is_trimmed(): void
    {
        $this->actingAs($this->administrator());
        Livewire::test(Form::class)->set('name', '  Producto nuevo  ')->set('units_per_container', '24')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('configuration.products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Producto nuevo', 'units_per_container' => 24]);
    }

    public function test_name_is_required_unique_and_limited_to_255_characters(): void
    {
        $this->actingAs($this->administrator());
        Product::factory()->create(['name' => 'Existente']);
        Livewire::test(Form::class)->set('name', '  ')->call('save')->assertHasErrors(['name' => 'required']);
        Livewire::test(Form::class)->set('name', ' Existente ')->call('save')->assertHasErrors(['name' => 'unique']);
        Livewire::test(Form::class)->set('name', str_repeat('a', 256))->call('save')->assertHasErrors(['name' => 'max']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_units_per_container_are_optional_and_must_be_a_positive_integer(): void
    {
        $this->actingAs($this->administrator());
        Livewire::test(Form::class)->set('name', 'Sin cantidad')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Sin cantidad', 'units_per_container' => null]);

        foreach (['0', '-1', '1.5', '2147483648'] as $value) {
            Livewire::test(Form::class)->set('name', 'Producto '.$value)->set('units_per_container', $value)
                ->call('save')->assertHasErrors('units_per_container');
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_editing_accepts_the_same_name_but_rejects_another_products_name(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create(['name' => 'Original']);
        Product::factory()->create(['name' => 'Ocupado']);
        Livewire::test(Form::class, ['product' => $product])->call('save')->assertHasNoErrors();
        Livewire::test(Form::class, ['product' => $product])->set('name', 'Ocupado')->call('save')->assertHasErrors('name');
        Livewire::test(Form::class, ['product' => $product])->set('name', 'Actualizado')->call('save')->assertHasNoErrors();
        $this->assertSame('Actualizado', $product->fresh()->name);
    }

    public function test_administrator_can_delete_a_product_from_its_form(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create();
        Livewire::test(Form::class, ['product' => $product])->call('delete')->assertRedirect(route('configuration.products.index'));
        $this->assertModelMissing($product);
    }

    public function test_search_filters_products_and_resets_pagination(): void
    {
        $this->actingAs($this->administrator());
        Product::factory()->count(16)->create();
        Product::factory()->create(['name' => 'Producto especial']);
        Livewire::test(Index::class)->call('setPage', 2)->set('search', 'Producto especial')
            ->assertSet('paginators.page', 1)->assertSee('Producto especial')->assertViewHas('products', fn ($products) => $products->total() === 1);
        Livewire::test(Index::class)->set('search', 'Sin coincidencias')->assertSee('No se encontraron productos');
    }

    public function test_authorization_is_checked_again_when_saving_or_deleting(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        $product = Product::factory()->create();
        $form = Livewire::test(Form::class, ['product' => $product]);
        $admin->role = UserRole::User;
        $admin->save();
        $form->call('delete')->assertForbidden();
        $this->assertModelExists($product);
    }

    public function test_missing_products_return_not_found(): void
    {
        $this->actingAs($this->administrator())->get('/configuracion/productos/999999/editar')->assertNotFound();
        Livewire::test(Form::class)->call('delete')->assertNotFound();
    }
}
