<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Users\Form;
use App\Livewire\Pages\Configuration\Users\Index;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['role' => UserRole::Administrator, 'last_name' => 'Principal']);
    }

    private function fillForm(array $overrides = []): array
    {
        return array_replace([
            'name' => 'María', 'last_name' => 'Pérez', 'email' => 'maria@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'role' => 'usuario', 'is_active' => true,
        ], $overrides);
    }

    public function test_login_and_home_use_the_reference_design_and_brand(): void
    {
        $this->get('/')->assertOk()->assertSee('Comercio-Exterior')->assertSee('El inicio de una gran idea.');
        $this->get(route('login'))->assertOk()->assertSee('Bienvenido de nuevo')->assertSee('data-theme-toggle', false)->assertDontSee('Sign up');
    }

    public function test_user_management_routes_require_an_administrator(): void
    {
        $target = User::factory()->create();
        $routes = [route('configuration.users.index'), route('configuration.users.create'), route('configuration.users.edit', $target)];

        foreach ($routes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs($target);
        foreach ($routes as $url) {
            $this->get($url)->assertForbidden();
        }

        Livewire::test(Form::class)->assertForbidden();
        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_administrators_can_render_the_pages_and_configuration_menu(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        $this->get(route('dashboard'))->assertOk()->assertSee('Configuración')->assertSee('Usuarios')->assertDontSee('Biométrico')->assertDontSee('Marcar entrada');
        $this->get(route('configuration.users.index'))->assertOk()->assertSee($admin->email);
        $this->get(route('configuration.users.create'))->assertOk()->assertSee('Apellido');
        $this->get(route('configuration.users.edit', $admin))->assertOk()->assertSee('Editar usuario');
    }

    public function test_regular_users_have_configuration_without_user_management(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertSee('Panorama de embarques')->assertSee('configuration-menu')->assertDontSee(route('configuration.users.index'));
    }

    public function test_listing_links_to_the_user_form_and_editing_loads_the_selected_user(): void
    {
        $this->actingAs($this->administrator());
        $user = User::factory()->create(['name' => 'Ana', 'last_name' => 'García']);
        $editUrl = route('configuration.users.edit', $user);

        $this->get(route('configuration.users.index'))
            ->assertOk()
            ->assertSee('data-href="'.$editUrl.'"', false)
            ->assertSee('href="'.$editUrl.'"', false)
            ->assertSee('href="'.route('configuration.users.create').'"', false);

        $this->get($editUrl)->assertOk()->assertSee('Guardar cambios');
        Livewire::test(Form::class, ['user' => $user])
            ->assertSet('userId', $user->id)
            ->assertSet('name', 'Ana')
            ->assertSet('last_name', 'García')
            ->assertSet('email', $user->email);
    }

    public function test_admin_can_create_users_with_a_hashed_password_and_normalized_email(): void
    {
        $this->actingAs($this->administrator());
        Livewire::test(Form::class)->fill($this->fillForm(['email' => 'MARIA@EXAMPLE.COM']))
            ->call('save')->assertHasNoErrors()->assertRedirect(route('configuration.users.index'));

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('María Pérez', $user->full_name);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_admin_can_create_another_administrator(): void
    {
        $this->actingAs($this->administrator());
        Livewire::test(Form::class)->fill($this->fillForm(['role' => 'administrador']))->call('save')->assertHasNoErrors();
        $this->assertTrue(User::where('email', 'maria@example.com')->firstOrFail()->isAdministrator());
    }

    public function test_new_users_without_permissions_can_access_the_original_home(): void
    {
        $this->actingAs($this->administrator());
        Livewire::test(Form::class)
            ->assertSet('access_international_trade', false)
            ->assertSet('access_foreign_trade', false)
            ->assertSet('access_configuration', false)
            ->fill($this->fillForm())->call('save')->assertHasNoErrors();

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertFalse($user->access_international_trade);
        $this->assertFalse($user->access_foreign_trade);
        $this->assertFalse($user->access_configuration);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Bienvenido, '.$user->full_name)
            ->assertSee('Sesión activa')
            ->assertDontSee('international-trade-menu')
            ->assertDontSee('foreign-trade-menu')
            ->assertDontSee('configuration-menu')
            ->assertDontSee('Administrar usuarios');
        foreach (['msc.index', 'foreign-trade.msc.index', 'configuration.products.index', 'configuration.users.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_user_fields_are_validated_and_email_must_be_unique(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        Livewire::test(Form::class)->fill($this->fillForm([
            'name' => ' ', 'last_name' => '', 'email' => $admin->email,
            'password' => 'short', 'password_confirmation' => 'different', 'role' => 'superadmin',
        ]))->call('save')->assertHasErrors(['name', 'last_name', 'email', 'password', 'role']);
        $this->assertSame(1, User::count());
    }

    public function test_admin_can_edit_a_user_without_changing_the_password(): void
    {
        $admin = $this->administrator();
        $target = User::factory()->create(['last_name' => 'García']);
        $originalPassword = $target->password;
        $this->actingAs($admin);

        Livewire::test(Form::class, ['user' => $target])->set('name', 'Ana')->set('role', 'administrador')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('Ana', $target->fresh()->name);
        $this->assertSame($originalPassword, $target->fresh()->password);
        $this->assertTrue($target->fresh()->isAdministrator());
    }

    public function test_admin_can_change_a_users_password_and_deactivate_the_account(): void
    {
        $this->actingAs($this->administrator());
        $target = User::factory()->create(['last_name' => 'García']);
        Livewire::test(Form::class, ['user' => $target])->set('password', 'Changed123!')->set('password_confirmation', 'Changed123!')
            ->set('is_active', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue(Hash::check('Changed123!', $target->fresh()->password));
    }

    public function test_admin_cannot_remove_their_own_access(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        Livewire::test(Form::class, ['user' => $admin])->set('role', 'usuario')->call('save')->assertHasErrors('role');
        Livewire::test(Form::class, ['user' => $admin])->set('is_active', false)->call('save')->assertHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdministrator());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_management_authorization_is_checked_again_during_livewire_actions(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        $component = Livewire::test(Form::class)->fill($this->fillForm());
        $admin->role = UserRole::User;
        $admin->save();
        $component->call('save')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_search_and_role_filter_return_only_matching_users(): void
    {
        $this->actingAs($this->administrator());
        User::factory()->create(['name' => 'María', 'last_name' => 'Pérez', 'email' => 'maria@example.com']);
        $other = User::factory()->create(['last_name' => 'García']);
        Livewire::test(Index::class)->set('search', 'Pérez')->assertSee('maria@example.com')->assertDontSee($other->email);
        Livewire::test(Index::class)->set('role', 'administrador')->assertDontSee('maria@example.com');
    }

    public function test_inactive_users_cannot_login_or_keep_using_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_roles_and_status_cannot_be_mass_assigned_from_profile_data(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => 'administrador', 'is_active' => false]);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_administrator_cannot_delete_their_account_from_profile(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertHasErrors('password');
        $this->assertModelExists($admin);
    }

    public function test_nonexistent_user_cannot_be_edited(): void
    {
        $this->actingAs($this->administrator())->get('/configuracion/usuarios/99999/editar')->assertNotFound();
    }
}
