<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Console\Commands;

use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Console\Command;

/**
 * A partir de la migración que agregó user_id a `apoderados`, todo
 * apoderado nuevo recibe su cuenta del portal automáticamente (ver
 * MatriculaService::registrarApoderado()). Este comando es solo para
 * los que ya existían antes de ese cambio: es seguro correrlo varias
 * veces, ya que MatriculaService reutiliza la cuenta si el DNI ya la
 * tiene y no vuelve a tocar los que ya quedaron vinculados.
 */
class GenerarAccesosApoderados extends Command
{
    protected $signature = 'apoderados:generar-accesos';

    protected $description = 'Crea (o vincula) el acceso al portal para los apoderados registrados antes de que existiera';

    public function handle(MatriculaService $matricula): int
    {
        $pendientes = Apoderado::query()->whereNull('user_id')->with('estudiante')->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay apoderados pendientes de acceso.');

            return self::SUCCESS;
        }

        $this->info("Generando acceso para {$pendientes->count()} apoderado(s)...");

        foreach ($pendientes as $apoderado) {
            $matricula->registrarApoderado($apoderado->estudiante, new RegistrarApoderadoData(
                nombres: $apoderado->nombres,
                dni: new Dni($apoderado->dni),
                celular: new Telefono($apoderado->celular),
                correo: $apoderado->correo,
                direccion: $apoderado->direccion,
                parentesco: $apoderado->parentesco,
            ));

            $this->line("  {$apoderado->nombres} (DNI {$apoderado->dni}) → {$apoderado->estudiante->nombreCompleto()}");
        }

        $this->info('Listo. Las contraseñas iniciales son el DNI de cada apoderado.');

        return self::SUCCESS;
    }
}
