<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identidad\Models\AuditLog;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_crear_un_usuario_registra_una_entrada_de_auditoria(): void
    {
        $nuevo = User::factory()->create();

        $entrada = AuditLog::query()
            ->where('auditable_type', $nuevo->getMorphClass())
            ->where('auditable_id', $nuevo->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($entrada);
    }

    public function test_actualizar_un_usuario_registra_una_entrada_de_auditoria(): void
    {
        $usuario = User::factory()->create();
        $usuario->update(['name' => 'Nombre actualizado']);

        $entrada = AuditLog::query()
            ->where('auditable_type', $usuario->getMorphClass())
            ->where('auditable_id', $usuario->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($entrada);
        $this->assertSame('Nombre actualizado', $entrada->new_values['name'] ?? null);
    }

    public function test_actualizar_la_contrasena_no_deja_el_hash_expuesto_en_la_auditoria(): void
    {
        $usuario = User::factory()->create();
        $hashOriginal = $usuario->password;

        $usuario->update(['password' => bcrypt('nueva-contrasena-segura')]);

        $entrada = AuditLog::query()
            ->where('auditable_type', $usuario->getMorphClass())
            ->where('auditable_id', $usuario->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($entrada);
        $this->assertSame('[oculto]', $entrada->new_values['password'] ?? null);
        $this->assertSame('[oculto]', $entrada->old_values['password'] ?? null);
        $this->assertStringNotContainsString($hashOriginal, json_encode($entrada->old_values));
        $this->assertStringNotContainsString($hashOriginal, json_encode($entrada->new_values));
    }

    public function test_administrativo_puede_ver_la_auditoria_y_docente_no(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get('/auditoria')
            ->assertOk();

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get('/auditoria')
            ->assertForbidden();
    }
}
