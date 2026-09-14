<?php

namespace Tests\Unit\Academico;

use App\Modules\Academico\Enums\ModalidadCicloEnum;
use PHPUnit\Framework\TestCase;

class ModalidadCicloEnumTest extends TestCase
{
    public function test_examenes_que_cuentan_es_seis_para_seis_meses(): void
    {
        $this->assertSame(6, ModalidadCicloEnum::SEIS_MESES->examenesQueCuentan());
    }

    public function test_examenes_que_cuentan_es_ocho_para_anual(): void
    {
        $this->assertSame(8, ModalidadCicloEnum::ANUAL->examenesQueCuentan());
    }
}
