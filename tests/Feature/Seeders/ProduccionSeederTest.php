<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Database\Seeders\ProduccionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduccionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_los_roles_y_la_cuenta_real_de_direccion(): void
    {
        $this->seed(ProduccionSeeder::class);

        $direccion = User::query()->where('email', 'walter.galindo@gmail.com')->first();

        $this->assertNotNull($direccion);
        $this->assertSame('Walter Galindo', $direccion->name);
        $this->assertSame('45139618', $direccion->dni);
        $this->assertSame(EstadoUsuarioEnum::ACTIVO, $direccion->estado);
        $this->assertTrue($direccion->hasRole(RolEnum::DIRECCION->value));
    }

    public function test_crea_las_cinco_cuentas_de_direccion_sin_datos_de_ejemplo(): void
    {
        $this->seed(ProduccionSeeder::class);

        $emails = [
            'walter.galindo@gmail.com',
            'diana.bautista@gmail.com',
            'aaron.galindo@gmail.com',
            'ruth.galindo@gmail.com',
            'reyna.galindo@gmail.com',
        ];

        foreach ($emails as $email) {
            $usuario = User::query()->where('email', $email)->first();

            $this->assertNotNull($usuario, "Falta la cuenta {$email}");
            $this->assertTrue($usuario->hasRole(RolEnum::DIRECCION->value));
        }

        $this->assertSame(5, User::query()->count());
    }

    public function test_correrlo_dos_veces_no_duplica_ninguna_cuenta(): void
    {
        $this->seed(ProduccionSeeder::class);
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(5, User::query()->count());
    }
}
