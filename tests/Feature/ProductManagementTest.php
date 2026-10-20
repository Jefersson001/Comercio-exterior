<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Products\Form;
use App\Livewire\Pages\Configuration\Products\Index;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\ProductVariant;
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

    public function test_multiple_product_categories_hide_variants_and_ignore_hidden_changes_without_deleting_history(): void
    {
        $this->actingAs($this->administrator());
        $multiple = Category::factory()->create(['allows_multiple_products' => true]);
        $individual = Category::factory()->create(['allows_multiple_products' => false]);
        $product = Product::factory()->create(['category_id' => $individual->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Color histórico']);
        Livewire::test(Form::class, ['product' => $product])
            ->assertSee('Variantes por color')->set('category_id', $multiple->id)
            ->assertDontSee('Variantes por color')
            ->call('addVariant')->assertCount('variants', 1)
            ->call('removeVariant', 0)->assertCount('variants', 1)
            ->set('variants', [['id' => null, 'color' => 'Color no permitido']])
            ->call('save')->assertHasNoErrors();
        $this->assertSame($multiple->id, $product->fresh()->category_id);
        $this->assertSame('Color histórico', $variant->fresh()->color);
        $this->assertSame(1, $product->variants()->count());
        $this->get(route('configuration.products.index'))->assertOk()->assertSee('No aplica')->assertDontSee('Color histórico');
        Livewire::test(Form::class, ['product' => $product->fresh()])
            ->set('category_id', $individual->id)->assertSee('Variantes por color')
            ->assertSet('variants', [['id' => $variant->id, 'color' => 'Color histórico']]);
        Livewire::test(Form::class)->set('name', 'Producto sin variantes')->set('category_id', $multiple->id)
            ->set('variants', [['color' => 'No permitido']])->call('save')->assertHasNoErrors();
        $this->assertSame(0, Product::where('name', 'Producto sin variantes')->firstOrFail()->variants()->count());
    }

    public function test_product_information_is_limited_to_name(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'name', 'units_per_container', 'category_id', 'created_at', 'updated_at'], Schema::getColumnListing('products'));
        $this->assertEqualsCanonicalizing(
            ['id', 'product_id', 'color', 'created_at', 'updated_at'],
            Schema::getColumnListing('product_variants'),
        );
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

    public function test_product_category_can_be_assigned_changed_and_cleared(): void
    {
        $this->actingAs($this->administrator());
        $category = Category::factory()->create();
        Livewire::test(Form::class)->set('name', 'Repuesto categorizado')
            ->set('category_id', $category->id)->call('save')->assertHasNoErrors();
        $product = Product::where('name', 'Repuesto categorizado')->firstOrFail();
        $this->assertTrue($product->category->is($category));
        Livewire::test(Form::class, ['product' => $product])
            ->assertSet('category_id', $category->id)->assertSee($category->name)
            ->set('category_id', 999999)->call('save')->assertHasErrors('category_id')
            ->set('category_id', null)->call('save')->assertHasNoErrors();
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_administrator_can_open_tree_create_and_edit_views(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create(['name' => 'Producto de prueba']);
        $editUrl = route('configuration.products.edit', $product);
        $this->get(route('configuration.products.index'))->assertOk()->assertSee($product->name)
            ->assertSee('data-href="'.$editUrl.'"', false)->assertSee('href="'.$editUrl.'"', false);
        $this->get(route('configuration.products.create'))
            ->assertOk()
            ->assertSee('Nombre *')
            ->assertSee('Variantes por color')
            ->assertSee('Agregar una línea')
            ->assertSee('No hay colores agregados.')
            ->assertDontSee('Agregar variante')
            ->assertDontSee('Eliminar producto');
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

    public function test_administrator_can_create_a_product_with_color_variants(): void
    {
        $this->actingAs($this->administrator());

        Livewire::test(Form::class)
            ->set('name', 'Camisa')
            ->set('units_per_container', '24')
            ->call('addVariant')
            ->assertSee('placeholder="Escribe el color..."', false)
            ->set('variants.0.color', ' Rojo ')
            ->call('addVariant')
            ->set('variants.1.color', 'Azul')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('configuration.products.index'));

        $product = Product::where('name', 'Camisa')->firstOrFail();
        $this->assertSame([
            ['color' => 'Rojo'],
            ['color' => 'Azul'],
        ], $product->variants->map(fn (ProductVariant $variant): array => [
            'color' => $variant->color,
        ])->all());
    }

    public function test_administrator_can_edit_and_remove_product_variants(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create();
        $red = $product->variants()->create(['color' => 'Rojo']);
        $blue = $product->variants()->create(['color' => 'Azul']);

        Livewire::test(Form::class, ['product' => $product])
            ->assertSet('variants', [
                ['id' => $red->id, 'color' => 'Rojo'],
                ['id' => $blue->id, 'color' => 'Azul'],
            ])
            ->call('removeVariant', 1)
            ->call('addVariant')
            ->set('variants.1.color', 'Verde')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([
            ['color' => 'Rojo'],
            ['color' => 'Verde'],
        ], $product->fresh()->variants->map(fn (ProductVariant $variant): array => [
            'color' => $variant->color,
        ])->all());
        $this->assertModelMissing($blue);
    }

    public function test_product_form_prevents_removing_variant_used_by_msc(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['color' => 'Rojo']);
        $shipment = MscShipment::factory()->create(['product_id' => $product->id]);
        $shipment->productVariantLines()->create(['product_variant_id' => $variant->id, 'quantity' => 3]);

        Livewire::test(Form::class, ['product' => $product])
            ->set('variants', [])
            ->call('save')
            ->assertHasErrors(['variants' => 'No se puede eliminar un color asociado a un embarque MSC. Retira primero la variante del embarque.']);

        $this->assertModelExists($variant);
        $this->assertDatabaseHas('msc_shipment_product_variants', [
            'msc_shipment_id' => $shipment->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_variant_colors_are_required_and_unique_per_product(): void
    {
        $this->actingAs($this->administrator());

        Livewire::test(Form::class)
            ->call('addVariant')
            ->set('variants.0.color', '')
            ->call('addVariant')
            ->set('variants.1.color', 'Rojo')
            ->call('addVariant')
            ->set('variants.2.color', ' rojo ')
            ->set('name', 'Producto con variantes inválidas')
            ->call('save')
            ->assertHasErrors([
                'variants.0.color',
                'variants.2.color',
            ]);

        $this->assertDatabaseMissing('products', ['name' => 'Producto con variantes inválidas']);
    }

    public function test_product_form_rejects_variant_ids_owned_by_another_product(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create(['name' => 'Producto propio']);
        $otherVariant = Product::factory()->create(['name' => 'Otro producto'])
            ->variants()->create(['color' => 'Negro']);

        Livewire::test(Form::class, ['product' => $product])
            ->set('variants', [
                ['id' => $otherVariant->id, 'color' => 'Blanco'],
            ])
            ->call('save')
            ->assertHasErrors('variants.0.id');

        $this->assertSame([], $product->fresh()->variants->all());
        $this->assertModelExists($otherVariant);
    }

    public function test_product_list_shows_variant_colors(): void
    {
        $this->actingAs($this->administrator());
        $product = Product::factory()->create(['name' => 'Producto con color']);
        $product->variants()->create(['color' => 'Morado']);

        $this->get(route('configuration.products.index'))
            ->assertOk()
            ->assertSee('Variantes')
            ->assertSee('Morado')
            ->assertDontSee('Inventario')
            ->assertDontSee('SKU')
            ->assertDontSee('disponibles');
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
        $variant = $product->variants()->create(['color' => 'Blanco']);
        Livewire::test(Form::class, ['product' => $product])->call('delete')->assertRedirect(route('configuration.products.index'));
        $this->assertModelMissing($product);
        $this->assertModelMissing($variant);
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
