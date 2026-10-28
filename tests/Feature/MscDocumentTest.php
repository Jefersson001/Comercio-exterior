<?php

namespace Tests\Feature;

use App\Livewire\Pages\Msc\Form;
use App\Models\Category;
use App\Models\MscShipment;
use App\Models\MscShipmentDocument;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MscDocumentTest extends TestCase
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

    public function test_can_navigate_to_documents_tab_and_see_empty_state(): void
    {
        $shipment = $this->foreignShipment();

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->assertSet('activeForeignTradeTab', 'documents')
            ->assertSee('Adjuntar nuevo documento')
            ->assertSee('No hay documentos registrados');
    }

    public function test_can_upload_a_document_to_an_existing_shipment(): void
    {
        Storage::fake('local');
        $shipment = $this->foreignShipment();

        $file = UploadedFile::fake()->create('factura_comercial.pdf', 150, 'application/pdf');

        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->set('newDocumentFile', $file)
            ->set('newDocumentName', 'Factura Comercial #102')
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->assertSee('Factura Comercial #102')
            ->assertSee('factura_comercial.pdf')
            ->assertSee('Documento guardado correctamente.');

        $this->assertDatabaseCount('msc_shipment_documents', 1);
        $document = MscShipmentDocument::first();
        $this->assertSame($shipment->id, $document->msc_shipment_id);
        $this->assertSame('Factura Comercial #102', $document->name);
        $this->assertSame('factura_comercial.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_can_upload_subsequent_documents_over_time(): void
    {
        Storage::fake('local');
        $shipment = $this->foreignShipment();

        // Subir primer documento "hoy"
        $file1 = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->set('newDocumentFile', $file1)
            ->set('newDocumentName', 'Factura de Proveedor')
            ->call('uploadDocument')
            ->assertHasNoErrors();

        // Regresar "otro día" y subir un segundo documento (ej. BL)
        $file2 = UploadedFile::fake()->create('bl_copia.pdf', 250, 'application/pdf');
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->assertSee('Factura de Proveedor')
            ->set('newDocumentFile', $file2)
            ->set('newDocumentName', 'Copia BL Original')
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->assertSee('Factura de Proveedor')
            ->assertSee('Copia BL Original')
            ->assertSee('Documentos del embarque (2)');

        $this->assertDatabaseCount('msc_shipment_documents', 2);
    }

    public function test_can_delete_an_uploaded_document(): void
    {
        Storage::fake('local');
        $shipment = $this->foreignShipment();

        $file = UploadedFile::fake()->create('doc_a_eliminar.pdf', 100, 'application/pdf');
        $component = Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->set('newDocumentFile', $file)
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = MscShipmentDocument::first();
        Storage::disk('local')->assertExists($document->file_path);

        $component->call('deleteDocument', $document->id)
            ->assertSee('Documento eliminado correctamente.');

        $this->assertDatabaseCount('msc_shipment_documents', 0);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_can_download_an_uploaded_document(): void
    {
        Storage::fake('local');
        $shipment = $this->foreignShipment();

        $file = UploadedFile::fake()->create('packing_list.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('selectForeignTradeTab', 'documents')
            ->set('newDocumentFile', $file)
            ->set('newDocumentName', 'Lista de Empaque')
            ->call('uploadDocument');

        $document = MscShipmentDocument::first();

        // Probar descarga mediante Livewire
        $response = Livewire::test(Form::class, ['shipment' => $shipment, 'foreignTrade' => true])
            ->call('downloadDocument', $document->id);

        $response->assertFileDownloaded('packing_list.xlsx');

        // Probar descarga mediante la ruta directa
        $this->get(route('foreign-trade.msc.documents.download', [$shipment, $document]))
            ->assertOk()
            ->assertDownload('packing_list.xlsx');
    }

    public function test_can_attach_pending_document_when_creating_new_shipment(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $file = UploadedFile::fake()->create('proforma.pdf', 120, 'application/pdf');

        Livewire::test(Form::class, ['foreignTrade' => true])
            ->set('category_id', $category->id)
            ->set('product_id', $product->id)
            ->set('productLines', [['product_id' => $product->id, 'container_qty' => '1', 'units_qty' => '']])
            ->set('eta', '2026-11-20')
            ->set('bl_master', 'BL-DOC-123')
            ->set('free_time', '15')
            ->set('container_qty', '1')
            ->call('selectForeignTradeTab', 'documents')
            ->set('newDocumentFile', $file)
            ->set('newDocumentName', 'Factura Proforma')
            ->call('uploadDocument')
            ->assertSee('Pendiente de guardar')
            ->assertSee('Factura Proforma')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('msc_shipment_documents', 1);
        $document = MscShipmentDocument::first();
        $this->assertSame('Factura Proforma', $document->name);
        Storage::disk('local')->assertExists($document->file_path);
    }
}
