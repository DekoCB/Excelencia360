<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Database\Seeders\AcademicoDemoSeeder;
use App\Modules\AulaVirtual\Database\Seeders\AulaVirtualDemoSeeder;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Database\Seeders\MatriculaDemoSeeder;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Este seeder crea cuentas @ceba.test y datos ficticios (estudiantes,
        // pagos, evaluaciones...) pensados solo para desarrollo local. Correrlo
        // contra producción mezclaría eso con la base de datos real del
        // colegio -- ahí corresponde ProduccionSeeder en su lugar, que solo
        // siembra roles/permisos y la cuenta real de Dirección.
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'DatabaseSeeder es solo para desarrollo local. En producción usa: php artisan db:seed --class=Database\\Seeders\\ProduccionSeeder'
            );
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $direccion = User::factory()->create([
            'name' => 'Dirección CEBA',
            'email' => 'direccion@ceba.test',
            'dni' => '00000001',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $docente = User::factory()->create([
            'name' => 'Docente Demo',
            'email' => 'docente@ceba.test',
            'dni' => '00000002',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $docente->assignRole(RolEnum::DOCENTE->value);

        $estudiante = User::factory()->create([
            'name' => 'Estudiante Demo',
            'email' => 'estudiante@ceba.test',
            'dni' => '00000003',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);

        $coordinador = User::factory()->create([
            'name' => 'Coordinador Demo',
            'email' => 'coordinador@ceba.test',
            'dni' => '00000004',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $tesoreria = User::factory()->create([
            'name' => 'Tesorería Demo',
            'email' => 'tesoreria@ceba.test',
            'dni' => '00000005',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $administrativo = User::factory()->create([
            'name' => 'Administrativo Demo',
            'email' => 'administrativo@ceba.test',
            'dni' => '00000006',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->call(AcademicoDemoSeeder::class);
        $this->call(MatriculaDemoSeeder::class);
        $this->call(AulaVirtualDemoSeeder::class);
        $this->call(DemoRobustoSeeder::class);
    }
}
