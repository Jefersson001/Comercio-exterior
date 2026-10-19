<?php

namespace App\Livewire\Pages\Configuration\Categories;

use App\Actions\Categories\ImportCategories;
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
        Gate::authorize('manage-categories');
    }

    public function import(ImportCategories $importer): void
    {
        Gate::authorize('manage-categories');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:10240']], [
            'file.required' => 'Selecciona el archivo de categorías.',
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
        session()->flash('status', "Importación completada: {$result['imported']} categorías cargados y {$result['skipped']} filas vacías o duplicadas omitidas.");
        $this->redirectRoute('configuration.categories.index');
    }

    public function render(): View
    {
        return view('pages.configuration.categories.import')
            ->layout('layouts.dashboard', ['title' => 'Importar categorías', 'heading' => 'Importar categorías']);
    }
}
