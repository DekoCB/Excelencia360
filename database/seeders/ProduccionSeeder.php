<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder para el primer despliegue en producción: solo los roles/permisos
 * base y las cuentas reales de Dirección, sin nada de datos de ejemplo. A
 * diferencia de DatabaseSeeder (pensado para desarrollo local, mezcla lo
 * anterior con estudiantes/pagos/evaluaciones ficticios vía
 * DemoRobustoSeeder y compañía), este es el único seeder que corresponde
 * correr contra la base de datos real del colegio.
 *
 * Uso (una sola vez, tras el primer `migrate --force` en Hostinger; correrlo
 * de nuevo más adelante es seguro -- cada cuenta ya creada se salta sin
 * duplicarse ni pisar su contraseña):
 *   php artisan db:seed --class=Database\\Seeders\\ProduccionSeeder --force
 *
 * Cada contraseña se genera al azar y se imprime una sola vez en la consola
 * -- no queda guardada en ningún lado más que en el hash de la BD. Cámbiala
 * apenas inicies sesión, o usa "¿Olvidó su contraseña?" en vez de la
 * impresa si el correo saliente (sección 2 de docs/DESPLIEGUE.md) ya está
 * configurado.
 */
class ProduccionSeeder extends Seeder
{
    /**
     * @var list<array{name: string, email: string, dni: string}>
     */
    private const CUENTAS_DIRECCION = [
        ['name' => 'Walter Galindo', 'email' => 'walter.galindo@gmail.com', 'dni' => '45139618'],
        ['name' => 'Diana Marifer Bautista Garcia', 'email' => 'diana.bautista@gmail.com', 'dni' => '73721183'],
        ['name' => 'Aaron Galindo Conde', 'email' => 'aaron.galindo@gmail.com', 'dni' => '71294422'],
        ['name' => 'Ruth Esther Galindo Conde', 'email' => 'ruth.galindo@gmail.com', 'dni' => '61144254'],
        ['name' => 'Reyna Galindo Conde', 'email' => 'reyna.galindo@gmail.com', 'dni' => '62262684'],
    ];

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        foreach (self::CUENTAS_DIRECCION as $cuenta) {
            $this->crearCuentaDireccion($cuenta['name'], $cuenta['email'], $cuenta['dni']);
        }
    }

    private function crearCuentaDireccion(string $name, string $email, string $dni): void
    {
        if (User::query()->where('email', $email)->exists()) {
            $this->command->warn("La cuenta de Dirección {$email} ya existe -- no se vuelve a crear.");

            return;
        }

        $contrasenaTemporal = Str::password(24);

        $direccion = User::query()->create([
            'name' => $name,
            'email' => $email,
            'dni' => $dni,
            'password' => Hash::make($contrasenaTemporal),
            'email_verified_at' => now(),
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->command->info("Cuenta de Dirección creada: {$email}");
        $this->command->warn("Contraseña temporal (cámbiala al iniciar sesión): {$contrasenaTemporal}");
    }
}
