<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\PublicacionService;
use App\Modules\AulaVirtual\Services\TareaService;
use Illuminate\Database\Seeder;

class AulaVirtualDemoSeeder extends Seeder
{
    public function run(): void
    {
        $docente = User::query()->where('email', 'docente@ceba.test')->first();

        if (! $docente) {
            return;
        }

        $horario = Horario::query()->where('docente_id', $docente->id)->orderBy('id')->first();

        if (! $horario) {
            return;
        }

        $cursoVirtualService = app(CursoVirtualService::class);
        $curso = $cursoVirtualService->activarParaHorario($horario);

        // Activa también los demás horarios del mismo curso, grado y ciclo
        // (si los hay) -- así el checklist "subir también a" tiene con qué
        // poblarse en la demo, en vez de quedar oculto por no encontrar
        // más de un curso virtual relacionado.
        Horario::query()
            ->where('curso_id', $horario->curso_id)
            ->where('grado_id', $horario->grado_id)
            ->where('ciclo_id', $horario->ciclo_id)
            ->where('id', '!=', $horario->id)
            ->get()
            ->each(fn (Horario $otro) => $cursoVirtualService->activarParaHorario($otro));

        app(MaterialService::class)->crear(
            $curso,
            TipoMaterialEnum::ENLACE,
            'Guía de lectura — unidad 1',
            'https://example.com/guia-unidad-1',
            null,
        );

        app(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo: comunicación efectiva',
            'descripcion' => 'Redactar un ensayo de una página sobre los principios de la comunicación efectiva vistos en clase.',
            'fecha_limite' => now()->addWeek()->toDateTimeString(),
            'puntaje_max' => 20,
        ]);

        app(PublicacionService::class)->crear(
            $curso,
            $docente->id,
            TipoPublicacionEnum::ANUNCIO,
            'Bienvenidos al curso. Revisen los materiales antes de la próxima clase.',
        );

        app(ForoService::class)->crear(
            $curso,
            $docente->id,
            'Dudas sobre la unidad 1',
            'Usen este espacio para consultar cualquier duda sobre los temas de la primera unidad.',
        );
    }
}
