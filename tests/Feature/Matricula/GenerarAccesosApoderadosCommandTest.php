<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GenerarAccesosApoderadosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_genera_acceso_para_los_apoderados_que_no_tenian(): void
    {
        $hijo = Estudiante::factory()->create();
        $apoderado = Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'dni' => '99887766', 'user_id' => null]);

        $this->artisan('apoderados:generar-accesos')->assertSuccessful();

        $usuario = User::query()->where('dni', '99887766')->first();
        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->hasRole(RolEnum::APODERADO->value));
        $this->assertTrue(Hash::check('99887766', $usuario->password));
        $this->assertSame($usuario->id, $apoderado->fresh()->user_id);
    }

    public function test_no_toca_a_los_que_ya_tienen_acceso(): void
    {
        $usuarioExistente = User::factory()->create(['dni' => '99887766']);
        $usuarioExistente->assignRole(RolEnum::APODERADO->value);

        $hijo = Estudiante::factory()->create();
        Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'dni' => '99887766', 'user_id' => $usuarioExistente->id]);

        $this->artisan('apoderados:generar-accesos')->assertSuccessful();

        $this->assertSame(1, User::query()->where('dni', '99887766')->count());
    }

    public function test_correrlo_dos_veces_no_duplica_cuentas(): void
    {
        $hijo = Estudiante::factory()->create();
        Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'dni' => '99887766', 'user_id' => null]);

        $this->artisan('apoderados:generar-accesos')->assertSuccessful();
        $this->artisan('apoderados:generar-accesos')->assertSuccessful();

        $this->assertSame(1, User::query()->where('dni', '99887766')->count());
    }
}
