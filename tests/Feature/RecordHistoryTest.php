<?php

namespace Tests\Feature;

use App\Actions\Categories\ImportCategories;
use App\Actions\Factories\ImportFactories;
use App\Actions\Msc\ImportMscShipments;
use App\Actions\Products\ImportProducts;
use App\Actions\Vessels\ImportVessels;
use App\Livewire\Pages\Configuration\Products\Form as ProductForm;
use App\Livewire\Pages\History\Index as HistoryIndex;
use App\Livewire\Pages\Msc\Form as MscForm;
use App\Livewire\RecordHistoryPanel;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\RecordChange;
use App\Models\User;
use App\Models\Vessel;
use App\Support\HistoryRecorder;
use App\Support\HistorySubject;
use App\Support\MscFields;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Tests\TestCase;

class RecordHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($user);

        return $user;
    }

    private function history(string $type, int $id)
    {
        return RecordChange::where('subject_type', $type)->where('subject_id', $id)->orderByDesc('id');
    }

    public function test_each_module_records_creator_editor_values_and_does_not_log_noop_saves(): void
    {
        $creator = $this->admin();
        foreach ([Category::factory()->create(), Product::factory()->create(), Factory::factory()->create(), Vessel::factory()->create(), User::factory()->create(), MscShipment::factory()->create()] as $record) {
            $type = HistorySubject::type($record);
            $created = $this->history($type, $record->id)->firstOrFail();
            $this->assertSame('created', $created->event);
            $this->assertSame($creator->id, $created->actor_id);
            $this->assertSame($creator->full_name, $created->actor_name);
            $count = $this->history($type, $record->id)->count();
            $record->save();
            $this->assertSame($count, $this->history($type, $record->id)->count());
            $field = $type === 'msc' ? 'released' : 'name';
            $record->$field = 'Modificado por historial';
            $record->save();
            $updated = $this->history($type, $record->id)->firstOrFail();
            $this->assertSame('updated', $updated->event);
            $this->assertSame('Modificado por historial', $updated->changes[$field]['after']);
        }
    }

    public function test_security_credentials_are_never_stored_and_security_only_changes_are_not_recorded(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $count = $this->history('user', $user->id)->count();
        $user->forceFill(['password' => 'NewPrivatePassword!', 'two_factor_secret' => 'PRIVATE-2FA', 'remember_token' => 'PRIVATE-TOKEN'])->save();
        $this->assertSame($count, $this->history('user', $user->id)->count());
        $json = $this->history('user', $user->id)->get()->toJson();
        foreach (['password', 'two_factor', 'remember_token', 'PRIVATE', $user->password] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_aggregate_saves_include_line_changes_once_and_ignore_line_recreation(): void
    {
        $this->admin();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $product->variants()->create(['color' => 'Azul']);
        $shipment = MscShipment::factory()->create(['category_id' => $category->id, 'product_id' => $product->id]);
        $shipment->syncSingleProductLine();
        $shipment->containers()->create(['name' => 'CONT-1']);
        $before = $this->history('msc', $shipment->id)->count();
        Livewire::test(MscForm::class, ['shipment' => $shipment, 'foreignTrade' => true])->call('save')->assertHasNoErrors();
        $this->assertSame($before, $this->history('msc', $shipment->id)->count());
        Livewire::test(MscForm::class, ['shipment' => $shipment->fresh(), 'foreignTrade' => true])
            ->set('productLines.0.container_qty', '3')->set('containerLines.0.name', 'CONT-2')
            ->call('save')->assertHasNoErrors();
        $this->assertSame($before + 1, $this->history('msc', $shipment->id)->count());
        $changes = $this->history('msc', $shipment->id)->firstOrFail()->changes;
        $this->assertStringContainsString('3 contenedores', $changes['products']['after']);
        $this->assertSame('CONT-1', $changes['containers']['before']);
        $this->assertSame('CONT-2', $changes['containers']['after']);

        $before = $this->history('product', $product->id)->count();
        Livewire::test(ProductForm::class, ['product' => $product])->set('variants.0.color', 'Rojo')->call('save')->assertHasNoErrors();
        $this->assertSame($before + 1, $this->history('product', $product->id)->count());
        $this->assertSame('Azul', $this->history('product', $product->id)->firstOrFail()->changes['colors']['before']);
        $this->assertSame('Rojo', $this->history('product', $product->id)->firstOrFail()->changes['colors']['after']);
    }

    public function test_transaction_rollbacks_remove_changes_and_reset_recorder_context(): void
    {
        $this->admin();
        $record = Factory::factory()->create(['name' => 'Original']);
        $count = $this->history('factory', $record->id)->count();
        try {
            app(HistoryRecorder::class)->batch(function () use ($record): void {
                $record->update(['name' => 'No debe persistir']);
                throw new RuntimeException('Rollback');
            }, 'import');
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback', $exception->getMessage());
        }
        $this->assertSame($count, $this->history('factory', $record->id)->count());
        $record->refresh()->update(['name' => 'Cambio posterior']);
        $this->assertSame('web', $this->history('factory', $record->id)->firstOrFail()->source);
        $this->assertSame('Original', $this->history('factory', $record->id)->firstOrFail()->changes['name']['before']);
    }

    public function test_all_imports_include_history_with_import_source_and_rollback_failed_files(): void
    {
        $actor = $this->admin();
        $product = Product::factory()->create(['name' => 'Modelo para importar']);
        $imports = [
            [ImportFactories::class, 'factory', [['Nombre'], ['Fábrica importada']]],
            [ImportVessels::class, 'vessel', [['Nombre'], ['Buque importado']]],
            [ImportCategories::class, 'category', [['Nombre'], ['Categoría importada']]],
            [ImportProducts::class, 'product', [['Nombre', 'Unidades por contenedor', 'Categoría', 'Variantes por color'], ['Producto importado', 24, 'Categoría importada', 'Azul // Rojo']]],
            [ImportMscShipments::class, 'msc', [array_values(MscFields::HEADERS), [1, '2026-10-01', '2026-11-01', 'IMPORT-HISTORY', '', '', $product->name, 1, '', 0, '', '', 'Borrador']]],
        ];
        foreach ($imports as [$action, $type, $rows]) {
            $path = $this->spreadsheet($rows);
            try {
                app($action)->handle($path);
                $entry = RecordChange::where('subject_type', $type)->where('source', 'import')->orderByDesc('id')->firstOrFail();
                $this->assertSame('created', $entry->event);
                $this->assertSame($actor->id, $entry->actor_id);
                if ($type === 'product') {
                    $this->assertStringContainsString('Rojo', $entry->changes['colors']['after']);
                }
            } finally {
                gc_collect_cycles();
                unlink($path);
            }
        }
        $before = RecordChange::count();
        $path = $this->spreadsheet([['Nombre'], ['No persistirá'], [str_repeat('X', 256)]]);
        try {
            app(ImportFactories::class)->handle($path);
            $this->fail('Invalid import should fail.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($before, RecordChange::count());
            $this->assertDatabaseMissing('factories', ['name' => 'No persistirá']);
        } finally {
            gc_collect_cycles();
            unlink($path);
        }
    }

    public function test_history_permissions_locked_ids_and_international_field_redaction(): void
    {
        $this->admin();
        $shipment = MscShipment::factory()->create();
        $shipment->update(['customs_agent_observation' => 'PRIVATE-EXTERIOR']);
        $user = User::factory()->create(['access_international_trade' => true, 'access_foreign_trade' => false, 'access_configuration' => false]);
        $this->actingAs($user);
        $panel = Livewire::test(RecordHistoryPanel::class, ['subjectType' => 'msc', 'subjectId' => $shipment->id]);
        $panel->assertOk()->assertDontSee('PRIVATE-EXTERIOR');
        $this->assertSame(1, $panel->viewData('entries')->total());
        Livewire::test(RecordHistoryPanel::class, ['subjectType' => 'msc', 'subjectId' => $shipment->id, 'foreignTrade' => true])->assertForbidden();
        Livewire::test(RecordHistoryPanel::class, ['subjectType' => 'user', 'subjectId' => $user->id])->assertForbidden();
        $this->get(route('configuration.history.index'))->assertForbidden();
        $user->access_international_trade = false;
        $user->save();
        $panel->call('$refresh')->assertForbidden();
    }

    public function test_deleted_records_remain_in_administrator_history_and_entries_are_read_only(): void
    {
        $actor = $this->admin();
        $factory = Factory::factory()->create(['name' => 'Fábrica eliminada']);
        $factory->delete();
        $entry = $this->history('factory', $factory->id)->firstOrFail();
        $this->assertSame('deleted', $entry->event);
        $this->get(route('configuration.history.index'))->assertOk()->assertSee('Fábrica eliminada');
        try {
            $entry->forceFill(['actor_name' => 'Cambio no permitido'])->save();
            $this->fail('History must be immutable.');
        } catch (LogicException) {
            $this->assertSame($actor->full_name, $entry->fresh()->actor_name);
        }
        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_a_history_write_failure_rolls_back_the_record_and_keeps_future_logging_usable(): void
    {
        $this->admin();
        $factory = Factory::factory()->create(['name' => 'Antes']);
        RecordChange::creating(fn () => throw new RuntimeException('History unavailable'));
        try {
            $factory->update(['name' => 'No debe persistir']);
            $this->fail('History failure should abort the operation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History unavailable', $exception->getMessage());
            $this->assertSame('Antes', $factory->fresh()->name);
        } finally {
            Event::forget('eloquent.creating: '.RecordChange::class);
        }
        $factory->refresh()->update(['name' => 'Después']);
        $this->assertSame('Después', $this->history('factory', $factory->id)->firstOrFail()->changes['name']['after']);
    }

    public function test_actor_names_survive_account_deletion_and_global_history_filters_records(): void
    {
        $actor = $this->admin();
        $name = $actor->full_name;
        $factory = Factory::factory()->create(['name' => 'Fábrica con autor']);
        $entry = $this->history('factory', $factory->id)->firstOrFail();
        $actor->delete();
        $this->assertSame($name, $entry->fresh()->actor_name);
        $this->assertNull($entry->fresh()->actor_id);
        $this->admin();
        Livewire::test(HistoryIndex::class)->set('type', 'factory')->set('search', 'Fábrica con autor')
            ->assertSee('Fábrica con autor')->assertSee($name)->assertDontSee('Correo electrónico');
    }

    public function test_record_history_identifiers_cannot_be_changed_from_the_browser(): void
    {
        $this->admin();
        $factory = Factory::factory()->create();
        $panel = Livewire::test(RecordHistoryPanel::class, ['subjectType' => 'factory', 'subjectId' => $factory->id]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $panel->set('subjectId', $factory->id + 1);
    }

    public function test_category_deletion_tracks_the_automatic_category_removal_from_products(): void
    {
        $actor = $this->admin();
        $category = Category::factory()->create(['name' => 'Categoría eliminada']);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $category->delete();
        $this->assertNull($product->fresh()->category_id);
        $entry = $this->history('product', $product->id)->firstOrFail();
        $this->assertSame($actor->id, $entry->actor_id);
        $this->assertSame('updated', $entry->event);
        $this->assertStringContainsString('Categoría eliminada', $entry->changes['category_id']['before']);
        $this->assertSame('—', $entry->changes['category_id']['after']);
    }

    private function spreadsheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'history-test-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
