@props(['historial', 'puedeExportar' => false, 'cicloLibreta' => null, 'cursosLibreta' => null, 'situacionFinalLibreta' => null])

{{--
    Bloque de solo lectura con todo lo que se sabe de un estudiante:
    grados cursados, pagos, documentos y notas. Extraído de
    historial-estudiante/index.blade.php (la búsqueda que usa
    Coordinación/Dirección) para que el portal de Apoderados
    (livewire/matricula/mis-hijos.blade.php) muestre exactamente lo mismo
    sobre su(s) hijo(s) sin duplicar este marcado -- ambos llaman a
    HistorialEstudianteService::porId() y renderizan el mismo resultado
    aquí. Los wire:click de acá (exportarPdf, exportarLibretaPdf) y el
    wire:model (cicloLibretaId) se resuelven contra el componente Livewire
    que lo incluya: cada uno debe implementarlos con ese mismo nombre.

    Es el "reporte general": la suma de los 4 bloques (académico,
    financiero, documentos) que historial-estudiante/index.blade.php
    también ofrece por separado como pestañas -- misma fuente para no
    duplicar marcado entre el PDF, el modo "General" y cada pestaña.
--}}
<x-historial-estudiante.encabezado :estudiante="$historial['estudiante']" />

<x-historial-estudiante.academico
    :historial="$historial"
    :puede-exportar="$puedeExportar"
    :ciclo-libreta="$cicloLibreta"
    :cursos-libreta="$cursosLibreta"
    :situacion-final-libreta="$situacionFinalLibreta"
/>

<x-historial-estudiante.financiero :historial="$historial" />

<x-historial-estudiante.documentos :historial="$historial" />
