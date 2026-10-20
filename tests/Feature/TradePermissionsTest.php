<?php

namespace Tests\Feature;

use App\Livewire\Pages\Configuration\Users\Form as UserForm;
use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Import;
use App\Livewire\Pages\Msc\Index;
use App\Models\User;
use App\Support\MscReportFields;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class TradePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_permission_combination_controls_routes_and_navigation(): void
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$international, $foreign]) {
            $user = User::factory()->create([
                'access_international_trade' => $international,
                'access_foreign_trade' => $foreign,
            ]);
            $this->actingAs($user);
            $dashboard = $this->get(route('dashboard'))->assertOk()->assertSee('configuration-menu');
            foreach ([['msc.', $international, 'international-trade-menu'], ['foreign-trade.msc.', $foreign, 'foreign-trade-menu']] as [$prefix, $allowed, $menu]) {
                $allowed ? $dashboard->assertSee($menu) : $dashboard->assertDontSee($menu);
                foreach (['index', 'create', 'import', 'export', 'export-direct', 'template'] as $action) {
                    $this->get(route($prefix.$action))->assertStatus($allowed ? 200 : 403);
                }
            }
            $this->get(route('configuration.products.index'))->assertOk();
            $this->get(route('configuration.users.index'))->assertForbidden();
            $dashboard->assertDontSee(route('configuration.users.index'));
        }
    }

    public function test_livewire_context_cannot_bypass_permissions_and_revocation_takes_effect(): void
    {
        $user = User::factory()->create(['access_international_trade' => false, 'access_foreign_trade' => true]);
        $this->actingAs($user);
        foreach ([Index::class, Form::class, Import::class] as $component) {
            Livewire::test($component)->assertForbidden();
            $page = Livewire::test($component, ['foreignTrade' => true])->assertOk();
            $user->access_international_trade = true;
            $user->access_foreign_trade = false;
            $user->save();
            $page->call('$refresh')->assertForbidden();
            $user->access_foreign_trade = true;
            $user->access_international_trade = false;
            $user->save();
        }
    }

    public function test_international_only_users_cannot_import_foreign_trade_sheets(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'permissions-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(MscReportFields::INTERNAL_HEADERS));
        $writer->addRow(Row::fromValues(['Dato no autorizado']));
        $writer->close();
        try {
            $upload = UploadedFile::fake()->createWithContent('msc.xlsx', file_get_contents($path));
            $this->actingAs(User::factory()->create(['access_international_trade' => true, 'access_foreign_trade' => false]));
            Livewire::test(Import::class)->set('file', $upload)->call('import')
                ->assertHasErrors('file')->assertSee('no tienes permiso')->assertSee('Fila 2');
            $this->assertDatabaseCount('msc_shipments', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_administrator_can_assign_and_change_all_three_checkboxes(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));
        Livewire::test(UserForm::class)
            ->set('name', 'Ana')->set('last_name', 'Pérez')->set('email', 'ana@example.com')
            ->set('password', 'Password123!')->set('password_confirmation', 'Password123!')
            ->set('access_international_trade', false)->set('access_foreign_trade', true)->set('access_configuration', true)
            ->call('save')->assertHasNoErrors();
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertFalse($user->access_international_trade);
        $this->assertTrue($user->access_foreign_trade);
        $this->assertTrue($user->access_configuration);
        Livewire::test(UserForm::class, ['user' => $user])
            ->assertSet('access_international_trade', false)->assertSet('access_foreign_trade', true)->assertSet('access_configuration', true)
            ->set('access_international_trade', true)->set('access_foreign_trade', false)->set('access_configuration', false)
            ->call('save')->assertHasNoErrors();
        $this->assertTrue($user->fresh()->access_international_trade);
        $this->assertFalse($user->fresh()->access_foreign_trade);
        $this->assertFalse($user->fresh()->access_configuration);
    }

    public function test_permission_attributes_cannot_be_mass_assigned_from_profile_data(): void
    {
        $user = User::factory()->create(['access_international_trade' => false, 'access_foreign_trade' => false, 'access_configuration' => false]);
        $user->fill(['access_international_trade' => true, 'access_foreign_trade' => true, 'access_configuration' => true]);
        $this->assertFalse($user->access_international_trade);
        $this->assertFalse($user->access_foreign_trade);
        $this->assertFalse($user->access_configuration);
    }

    public function test_administrators_keep_full_access_and_report_parameters_cannot_bypass_permissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator, 'access_international_trade' => false, 'access_foreign_trade' => false, 'access_configuration' => false]);
        $this->actingAs($admin)->get(route('msc.index'))->assertOk();
        $this->get(route('foreign-trade.msc.index'))->assertOk();
        $this->get(route('configuration.users.index'))->assertOk();
        $this->get(route('configuration.products.index'))->assertOk();
        $this->actingAs(User::factory()->create(['access_international_trade' => true, 'access_foreign_trade' => false]));
        foreach (['export', 'export-direct'] as $action) {
            $this->get(route('msc.'.$action, ['foreign_trade' => true]))->assertForbidden();
        }
    }
}
