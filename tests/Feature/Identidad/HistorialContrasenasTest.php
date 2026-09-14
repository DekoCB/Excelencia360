<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identidad\Services\HistorialContrasenaService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HistorialContrasenasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): HistorialContrasenaService
    {
        return $this->app->make(HistorialContrasenaService::class);
    }

    public function test_cambiar_la_contrasena_de_un_docente_aparece_en_personal_no_en_estudiantes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->update(['password' => bcrypt('nueva-contrasena')]);

        $personal = $this->service()->listar(soloEstudiantes: false);
        $estudiantes = $this->service()->listar(soloEstudiantes: true);

        $this->assertTrue($personal->getCollection()->contains(fn ($entrada) => $entrada->auditable_id === $docente->id));
        $this->assertFalse($estudiantes->getCollection()->contains(fn ($entrada) => $entrada->auditable_id === $docente->id));
    }

    public function test_cambiar_la_contrasena_de_un_estudiante_aparece_en_estudiantes_no_en_personal(): void
    {
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante->update(['password' => bcrypt('nueva-contrasena')]);

        $personal = $this->service()->listar(soloEstudiantes: false);
        $estudiantes = $this->service()->listar(soloEstudiantes: true);

        $this->assertFalse($personal->getCollection()->contains(fn ($entrada) => $entrada->auditable_id === $estudiante->id));
        $this->assertTrue($estudiantes->getCollection()->contains(fn ($entrada) => $entrada->auditable_id === $estudiante->id));
    }

    public function test_actualizar_otro_campo_sin_tocar_la_contrasena_no_aparece_en_el_historial(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->update(['name' => 'Nombre actualizado']);

        $personal = $this->service()->listar(soloEstudiantes: false);

        $this->assertFalse($personal->getCollection()->contains(fn ($entrada) => $entrada->auditable_id === $docente->id));
    }

    public function test_administrativo_puede_ver_el_historial_de_contrasenas_y_docente_no(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get('/historial-contrasenas')
            ->assertOk();

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get('/historial-contrasenas')
            ->assertForbidden();
    }

    public function test_la_pagina_cambia_de_categoria_entre_personal_y_estudiantes(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $docente = User::factory()->create(['name' => 'Docente Con Cambio']);
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->update(['password' => bcrypt('nueva-contrasena')]);

        $this->actingAs($administrativo);

        Volt::test('historial-contrasenas.index')
            ->assertSee('Docente Con Cambio')
            ->set('categoria', 'estudiantes')
            ->assertDontSee('Docente Con Cambio');
    }
}
