<?php

namespace App\Livewire\Pages\Configuration\Vessels;

use App\Actions\Vessels\ImportVessels;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class Import extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public function boot(): void
    {
        Gate::authorize('manage-vessels');
    }

    public function import(ImportVessels $importer): void
    {
        Gate::authorize('manage-vessels');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:10240']], [
            'file.required' => 'Selecciona el archivo de buques.',
            'file.mimes' => 'El archivo debe ser un Excel con formato .xlsx.',
            'file.extensions' => 'El archivo debe tener extensión .xlsx.',
            'file.max' => 'El archivo no debe superar los 10 MB.',
        ]);

        if ($this->file === null) {
            return;
        }

        try {
            $result = $importer->handle($this->file->getRealPath());
        } catch (InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'No se pudo importar el archivo. Comprueba que sea un Excel válido y que no esté dañado. No se guardaron cambios.');

            return;
        }

        $this->file->delete();
        $this->reset('file');
        session()->flash('status', "Importación completada: {$result['imported']} buques cargados y {$result['skipped']} filas vacías o duplicadas omitidas.");
        $this->redirectRoute('configuration.vessels.index');
    }

    public function render(): View
    {
        return view('pages.configuration.vessels.import')
            ->layout('layouts.dashboard', ['title' => 'Importar buques', 'heading' => 'Importar buques']);
    }
}
