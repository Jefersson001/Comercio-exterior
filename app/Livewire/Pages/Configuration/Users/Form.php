<?php

namespace App\Livewire\Pages\Configuration\Users;

use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = 'usuario';

    public bool $is_active = true;

    public bool $access_international_trade = false;

    public bool $access_foreign_trade = false;

    public bool $access_configuration = false;

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->last_name = $user->last_name;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->is_active = $user->is_active;
            $this->access_international_trade = $user->access_international_trade;
            $this->access_foreign_trade = $user->access_foreign_trade;
            $this->access_configuration = $user->access_configuration;
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-users');

        $this->name = trim($this->name);
        $this->last_name = trim($this->last_name);
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->userId)],
            'password' => [$this->userId ? 'nullable' : 'required', 'string', 'max:255', Password::min(8), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'access_international_trade' => ['required', 'boolean'],
            'access_foreign_trade' => ['required', 'boolean'],
            'access_configuration' => ['required', 'boolean'],
        ], attributes: [
            'name' => 'nombre', 'last_name' => 'apellido', 'email' => 'correo electrónico',
            'password' => 'contraseña', 'role' => 'rol', 'is_active' => 'estado',
        ]);

        if ($this->userId === auth()->id() && ($this->role !== UserRole::Administrator->value || ! $this->is_active)) {
            $this->addError('role', 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.');

            return;
        }

        $user = $this->userId ? User::findOrFail($this->userId) : new User;
        $user->fill(['name' => $this->name, 'last_name' => $this->last_name, 'email' => $this->email]);
        $user->role = UserRole::from($validated['role']);
        $user->is_active = $validated['is_active'];
        $user->access_international_trade = $validated['access_international_trade'];
        $user->access_foreign_trade = $validated['access_foreign_trade'];
        $user->access_configuration = $validated['access_configuration'];

        if ($this->password !== '') {
            $user->password = $this->password;
            $user->remember_token = Str::random(60);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->reset('password', 'password_confirmation');

        session()->flash('status', $this->userId ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
        if ($this->userId === null) {
            $this->redirectRoute('configuration.users.index');
        } else {
            $this->dispatch('record-saved');
        }
    }

    public function render(): View
    {
        $title = $this->userId ? 'Editar usuario' : 'Crear usuario';

        return view('pages.configuration.users.form', ['roles' => UserRole::cases()])
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }
}
