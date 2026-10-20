<?php

namespace App\Livewire\Pages\Msc;

use App\Actions\Msc\ImportMscShipments;
use App\Concerns\InteractsWithMscContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class Import extends Component
{
    use InteractsWithMscContext;
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public function boot(): void
    {
        Gate::authorize('manage-msc');
    }

    public function import(ImportMscShipments $importer): void
    {
        $this->authorizeMscContext();
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:10240']], [
            'file.required' => 'Selecciona el archivo MSC.',
            'file.mimes' => 'Selecciona un archivo Excel .xlsx válido.',
            'file.extensions' => 'El archivo debe tener extensión .xlsx.',
            'file.max' => 'El archivo no debe superar los 10 MB.',
        ]);
        if ($this->file === null) {
            return;
        }
        try {
            $result = $importer->handle($this->file->getRealPath(), foreignTrade: $this->foreignTrade, allowForeignTrade: Gate::allows('access-foreign-trade'));
        } catch (InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'No se pudo leer el archivo Excel. No se guardaron cambios.');

            return;
        }
        $this->file->delete();
        $this->reset('file');
        session()->flash('status', "Importación MSC completada: {$result['imported']} registros nuevos, {$result['updated']} actualizados y {$result['skipped']} filas vacías o de resumen omitidas.");
        $this->redirect($this->mscRoute('index'));
    }

    public function mount(bool $foreignTrade = false): void
    {
        $this->initializeMscContext($foreignTrade);
    }

    public function render(): View
    {
        return view('pages.msc.import')->layout('layouts.dashboard', ['title' => 'Importar MSC', 'heading' => 'Importar MSC']);
    }
}
