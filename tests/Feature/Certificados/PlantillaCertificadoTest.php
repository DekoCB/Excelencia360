<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlantillaCertificadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_para_tipo_crea_la_fila_con_valores_por_defecto_si_no_existe(): void
    {
        $this->assertDatabaseCount('plantilla_certificados', 0);

        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);

        $this->assertDatabaseCount('plantilla_certificados', 1);
        $this->assertSame('Certificado de estudios', $plantilla->titulo);
        $this->assertSame('#139896', $plantilla->color_acento);
    }

    public function test_para_tipo_siempre_devuelve_la_misma_fila(): void
    {
        $primera = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);
        $segunda = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertDatabaseCount('plantilla_certificados', 1);
    }

    public function test_cada_tipo_de_documento_tiene_su_propia_plantilla(): void
    {
        $certificado = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);
        $constancia = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA);

        $this->assertNotSame($certificado->id, $constancia->id);
        $this->assertNotSame($certificado->titulo, $constancia->titulo);
        $this->assertDatabaseCount('plantilla_certificados', 2);
    }

    /**
     * @return iterable<string, array{TipoDocumentoEnum}>
     */
    public static function tiposDeDocumento(): iterable
    {
        foreach (TipoDocumentoEnum::cases() as $tipo) {
            yield $tipo->value => [$tipo];
        }
    }

    /**
     * Todo caso del enum debe tener su arm en
     * PlantillaCertificado::valoresPorDefecto(), o esto lanza
     * UnhandledMatchError -- incluye explícitamente constancia_vacante
     * (retirada del módulo Constancias, pero sus registros antiguos siguen
     * necesitando poder resolver una plantilla).
     */
    #[DataProvider('tiposDeDocumento')]
    public function test_para_tipo_tiene_valores_por_defecto_para_todos_los_casos_del_enum(TipoDocumentoEnum $tipo): void
    {
        $plantilla = PlantillaCertificado::paraTipo($tipo);

        $this->assertSame($tipo, $plantilla->tipo);
        $this->assertNotSame('', $plantilla->titulo);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $plantilla->color_acento);
    }

    public function test_constancias_incluye_matricula_egresado_y_estudios_pero_no_vacante(): void
    {
        $valores = array_map(fn (TipoDocumentoEnum $tipo) => $tipo->value, TipoDocumentoEnum::constancias());

        $this->assertContains('constancia_estudios', $valores);
        $this->assertContains('constancia_matricula', $valores);
        $this->assertContains('constancia_egresado', $valores);
        $this->assertContains('constancia_buena_conducta', $valores);
        $this->assertNotContains('constancia_vacante', $valores);
    }

    public function test_guardar_plantilla_persiste_los_cambios(): void
    {
        $service = app(CertificadoService::class);

        $service->guardarPlantilla(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, [
            'institucion' => 'CEBA Peruano Británico',
            'titulo' => 'Constancia de estudios',
            'cuerpo' => 'Certificamos que {{estudiante}} ({{dni}}) {{detalle_matricula}}',
            'pie_nota' => 'Nota al pie personalizada.',
            'color_acento' => '#FF0000',
        ]);

        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);
        $this->assertSame('CEBA Peruano Británico', $plantilla->institucion);
        $this->assertSame('Constancia de estudios', $plantilla->titulo);
        $this->assertSame('#FF0000', $plantilla->color_acento);
    }

    public function test_guardar_plantilla_de_un_tipo_no_afecta_a_los_demas(): void
    {
        $service = app(CertificadoService::class);

        $service->guardarPlantilla(TipoDocumentoEnum::CONSTANCIA_VACANTE, [
            'institucion' => 'CEBA',
            'titulo' => 'Título editado',
            'cuerpo' => 'Cuerpo editado.',
            'pie_nota' => null,
            'color_acento' => '#000000',
        ]);

        $certificado = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);
        $this->assertSame('Certificado de estudios', $certificado->titulo);
    }

    public function test_renderizar_cuerpo_sustituye_los_placeholders_provistos(): void
    {
        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);

        $resultado = $plantilla->renderizarCuerpo([
            'estudiante' => 'Juan Pérez Ríos',
            'dni' => '87654321',
            'detalle_matricula' => 'cursó el grado 3ro de Secundaria,',
        ]);

        $this->assertStringContainsString('Juan Pérez Ríos', $resultado);
        $this->assertStringContainsString('87654321', $resultado);
        $this->assertStringContainsString('cursó el grado 3ro de Secundaria,', $resultado);
    }

    public function test_renderizar_cuerpo_no_ejecuta_sintaxis_blade_o_php_del_texto_editado(): void
    {
        // Regresión de seguridad: el texto que un administrativo escribe en
        // la plantilla NUNCA debe compilarse como Blade/PHP (eso sería
        // ejecución de código arbitrario) -- solo str_replace de
        // placeholders. Si alguien escribe algo con pinta de Blade, debe
        // quedar tal cual, como texto plano.
        $service = app(CertificadoService::class);
        $service->guardarPlantilla(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, [
            'institucion' => 'CEBA',
            'titulo' => 'Certificado',
            'cuerpo' => 'Resultado: {{ 7 * 7 }} y @php echo "hackeado"; @endphp para {{estudiante}}.',
            'pie_nota' => null,
            'color_acento' => '#137A6C',
        ]);

        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);
        $resultado = $plantilla->renderizarCuerpo(['estudiante' => 'Ana']);

        // Si se hubiera compilado como Blade/PHP, el resultado sería
        // "Resultado: 49 y hackeado para Ana." -- en vez de eso, el texto
        // literal debe quedar intacto, probando que nunca se ejecutó.
        $this->assertStringContainsString('{{ 7 * 7 }}', $resultado);
        $this->assertStringContainsString('@php echo "hackeado"; @endphp', $resultado);
        $this->assertStringNotContainsString('Resultado: 49 y hackeado', $resultado);
    }

    public function test_emitir_certificado_genera_pdf_valido_usando_la_plantilla_personalizada(): void
    {
        $service = app(CertificadoService::class);
        $service->guardarPlantilla(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, [
            'institucion' => 'CEBA Personalizado',
            'titulo' => 'Título Personalizado',
            'cuerpo' => 'Cuerpo personalizado para {{estudiante}}.',
            'pie_nota' => null,
            'color_acento' => '#123456',
        ]);

        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = $service->emitir($estudiante, null, null, null, $emisor);

        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_previsualizar_plantilla_devuelve_un_pdf_valido_sin_emitir_certificados(): void
    {
        $plantilla = PlantillaCertificado::paraTipo(TipoDocumentoEnum::CERTIFICADO_ESTUDIOS);

        $pdf = app(CertificadoService::class)->previsualizarPlantilla($plantilla);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertDatabaseCount('certificados', 0);
    }
}
