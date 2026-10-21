<?php

namespace App\Livewire\Pages\Configuration\Factories;

use App\Models\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $factoryId = null;

    public string $name = '';

    public function boot(): void
    {
        Gate::authorize('manage-factories');
    }

    public function mount(?Factory $factory = null): void
    {
        if ($factory?->exists) {
            $this->factoryId = $factory->id;
            $this->name = $factory->name;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-factories');
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Factory::class)->ignore($this->factoryId)],
        ], [
            'name.required' => 'Ingresa el nombre de la fábrica.',
            'name.unique' => 'Ya existe una fábrica con este nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
        ]);

        $factory = $this->factoryId ? Factory::findOrFail($this->factoryId) : new Factory;
        $factory->name = $this->name;
        $factory->save();

        session()->flash('status', $this->factoryId ? 'Fábrica actualizada correctamente.' : 'Fábrica creada correctamente.');
        if ($this->factoryId === null) {
            $this->redirectRoute('configuration.factories.index');
        } else {
            $this->dispatch('record-saved');
        }
    }

    public function delete(): void
    {
        Gate::authorize('manage-factories');
        abort_if($this->factoryId === null, 404);
        $factory = Factory::findOrFail($this->factoryId);
        if ($factory->mscShipments()->exists()) {
            $this->addError('name', 'No se puede eliminar una fábrica utilizada en MSC.');

            return;
        }
        $factory->delete();

        session()->flash('status', 'Fábrica eliminada correctamente.');
        $this->redirectRoute('configuration.factories.index');
    }

    public function render(): View
    {
        $title = $this->factoryId ? 'Editar fábrica' : 'Crear fábrica';

        return view('pages.configuration.factories.form')
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }
}
