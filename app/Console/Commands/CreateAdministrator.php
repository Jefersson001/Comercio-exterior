<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin')]
#[Description('Crear un administrador de Comercio-Exterior de forma interactiva')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta php artisan app:create-admin en una terminal interactiva.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Nombre')),
            'last_name' => trim((string) $this->ask('Apellido')),
            'email' => Str::lower(trim((string) $this->ask('Correo electrónico'))),
            'password' => (string) $this->secret('Contraseña (mínimo 8 caracteres)'),
            'password_confirmation' => (string) $this->secret('Confirmar contraseña'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'max:255', Password::min(8), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->fill(collect($validator->validated())->except('password_confirmation')->all());
        $user->role = UserRole::Administrator;
        $user->is_active = true;
        $user->save();

        $this->info('Administrador creado correctamente. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
