<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoCapacitacionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CertificadoService
    {
        return app(CertificadoService::class);
    }

    public function test_emitir_un_certificado_de_capacitacion_persiste_el_curso_y_el_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create(['nombre' => 'Ofimática Nivel Avanzado', 'horas_lectivas' => 130]);

        $certificado = $this->service()->emitir(
            $estudiante,
            null,
            null,
            null,
            $emisor,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
            $curso,
            '3002324002',
        );

        $this->assertSame($curso->id, $certificado->curso_capacitacion_id);
        $this->assertSame('3002324002', $certificado->numero_registro);
        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_verificar_encuentra_un_certificado_de_capacitacion_por_su_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $certificado = $this->service()->emitir(
            $estudiante,
            null,
            null,
            null,
            $emisor,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
            $curso,
            '3002324002',
        );

        $resultado = $this->service()->verificar('3002324002');

        $this->assertNotNull($resultado);
        $this->assertSame($certificado->id, $resultado->id);
    }

    public function test_verificar_por_codigo_de_verificacion_sigue_funcionando_para_certificados_academicos(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = $this->service()->emitir($estudiante, null, null, null, $emisor);

        $resultado = $this->service()->verificar($certificado->codigo_verificacion);

        $this->assertNotNull($resultado);
        $this->assertSame($certificado->id, $resultado->id);
    }

    public function test_verificar_un_numero_de_registro_inexistente_no_encuentra_nada(): void
    {
        $this->assertNull($this->service()->verificar('0000000000'));
    }

    public function test_duplicar_un_certificado_de_capacitacion_conserva_el_curso_y_el_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create();

        $original = $this->service()->emitir(
            $estudiante,
            null,
            null,
            null,
            $emisor,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
            $curso,
            '3002324002',
        );

        $duplicado = $this->service()->duplicar($original, null, $emisor);

        $this->assertSame($curso->id, $duplicado->curso_capacitacion_id);
        $this->assertSame('3002324002', $duplicado->numero_registro);
        $this->assertTrue($duplicado->es_duplicado);

        // verificar() solo debe devolver el original (es_duplicado=false),
        // nunca el duplicado, aunque comparta numero_registro.
        $resultado = $this->service()->verificar('3002324002');
        $this->assertSame($original->id, $resultado->id);
    }

    public function test_el_pdf_de_un_certificado_de_capacitacion_incluye_el_curso_las_horas_y_el_numero_de_registro(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $curso = CursoCapacitacion::factory()->create([
            'nombre' => 'Ofimática Nivel Avanzado',
            'horas_lectivas' => 130,
            'documento_autorizacion' => 'R.D.R. N°2182-2023-DREP',
        ]);

        $certificado = $this->service()->emitir(
            $estudiante,
            null,
            null,
            null,
            $emisor,
            TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
            $curso,
            '3002324002',
        );

        $certificado->load(['estudiante', 'cursoCapacitacion']);
        $plantilla = PlantillaCertificado::paraTipo($certificado->tipo);

        $html = view('pdf.certificado', [
            'certificado' => $certificado,
            'plantilla' => $plantilla,
            'cuerpo' => $plantilla->renderizarCuerpo([
                'estudiante' => $certificado->estudiante->nombreCompleto(),
                'dni' => $certificado->estudiante->dni,
                'curso' => $curso->nombre,
                'horas_lectivas' => (string) $curso->horas_lectivas,
            ]),
        ])->render();

        $this->assertStringContainsString('Ofimática Nivel Avanzado', $html);
        $this->assertStringContainsString('130 horas lectivas', $html);
        $this->assertStringContainsString('3002324002', $html);
        $this->assertStringContainsString('R.D.R. N°2182-2023-DREP', $html);
    }

    public function test_previsualizar_plantilla_de_capacitacion_no_falla_sin_certificado_real(): void
    {
        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_CAPACITACION);

        $pdf = $this->service()->previsualizarPlantilla($plantilla);

        $this->assertNotEmpty($pdf);
    }
}
