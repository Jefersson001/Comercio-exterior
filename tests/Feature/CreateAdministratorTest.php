<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_administrator_with_a_secret_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Ana')
            ->expectsQuestion('Apellido', 'Pérez')
            ->expectsQuestion('Correo electrónico', 'ANA@EXAMPLE.COM')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'Password123!')
            ->expectsQuestion('Confirmar contraseña', 'Password123!')
            ->expectsOutput('Administrador creado correctamente. Ya puedes iniciar sesión.')
            ->assertSuccessful();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue($user->isAdministrator());
        $this->assertTrue($user->is_active);
        $this->assertSame('Ana Pérez', $user->full_name);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_command_rejects_invalid_data(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Ana')->expectsQuestion('Apellido', 'Pérez')
            ->expectsQuestion('Correo electrónico', 'invalid')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'short')
            ->expectsQuestion('Confirmar contraseña', 'different')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
