<?php

namespace App\Livewire\Pages\Configuration\Vessels;

use App\Models\Vessel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $vesselId = null;

    public string $name = '';

    public function boot(): void
    {
        Gate::authorize('manage-vessels');
    }

    public function mount(?Vessel $vessel = null): void
    {
        if ($vessel?->exists) {
            $this->vesselId = $vessel->id;
            $this->name = $vessel->name;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-vessels');
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Vessel::class)->ignore($this->vesselId)],
        ], [
            'name.required' => 'Ingresa el nombre del buque.',
            'name.unique' => 'Ya existe un buque con este nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
        ]);

        $vessel = $this->vesselId ? Vessel::findOrFail($this->vesselId) : new Vessel;
        $vessel->name = $this->name;
        $vessel->save();

        session()->flash('status', $this->vesselId ? 'Buque actualizado correctamente.' : 'Buque creado correctamente.');
        if ($this->vesselId === null) {
            $this->redirectRoute('configuration.vessels.index');
        } else {
            $this->dispatch('record-saved');
        }
    }

    public function delete(): void
    {
        Gate::authorize('manage-vessels');
        abort_if($this->vesselId === null, 404);
        $vessel = Vessel::findOrFail($this->vesselId);
        if ($vessel->mscShipments()->exists()) {
            $this->addError('name', 'No se puede eliminar un buque utilizado en MSC.');

            return;
        }
        $vessel->delete();

        session()->flash('status', 'Buque eliminado correctamente.');
        $this->redirectRoute('configuration.vessels.index');
    }

    public function render(): View
    {
        $title = $this->vesselId ? 'Editar buque' : 'Crear buque';

        return view('pages.configuration.vessels.form')
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }
}
