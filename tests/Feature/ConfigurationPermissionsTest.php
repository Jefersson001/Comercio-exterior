<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigurationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_checkbox_controls_all_catalog_routes_independently_of_trade_access(): void
    {
        foreach ([false, true] as $allowed) {
            $this->actingAs(User::factory()->create([
                'access_configuration' => $allowed,
                'access_international_trade' => ! $allowed,
                'access_foreign_trade' => ! $allowed,
            ]));
            $dashboard = $this->get(route('dashboard'))->assertOk();
            $allowed ? $dashboard->assertSee('configuration-menu') : $dashboard->assertDontSee('configuration-menu');
            $dashboard->assertDontSee(route('configuration.users.index'));
            foreach (['products', 'categories', 'factories', 'vessels'] as $module) {
                foreach (['index', 'create', 'import', 'export', 'template'] as $action) {
                    $this->get(route("configuration.{$module}.{$action}"))->assertStatus($allowed ? 200 : 403);
                }
            }
            foreach (['index', 'create'] as $action) {
                $this->get(route('configuration.users.'.$action))->assertForbidden();
            }
        }
    }

    public function test_catalog_components_recheck_configuration_permission_after_it_is_revoked(): void
    {
        $user = User::factory()->create(['access_configuration' => true]);
        $this->actingAs($user);
        foreach (['Products', 'Categories', 'Factories', 'Vessels'] as $module) {
            foreach (['Index', 'Form', 'Import'] as $page) {
                $component = Livewire::test("App\\Livewire\\Pages\\Configuration\\{$module}\\{$page}")->assertOk();
                $user->access_configuration = false;
                $user->save();
                $component->call('$refresh')->assertForbidden();
                $user->access_configuration = true;
                $user->save();
            }
        }
    }
}
